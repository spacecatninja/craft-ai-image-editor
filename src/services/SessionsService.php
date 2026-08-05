<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\services;

use Craft;
use craft\base\Component;
use craft\base\FsInterface;
use craft\elements\Asset;
use craft\elements\User;
use craft\fs\Temp;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use spacecatninja\aiimageeditor\AiImageEditor;
use spacecatninja\aiimageeditor\db\Table;
use spacecatninja\aiimageeditor\enums\SessionStatus;
use spacecatninja\aiimageeditor\events\EditEvent;
use spacecatninja\aiimageeditor\exceptions\SessionException;
use spacecatninja\aiimageeditor\models\EditRequest;
use spacecatninja\aiimageeditor\models\EditResult;
use spacecatninja\aiimageeditor\models\EditSession;
use spacecatninja\aiimageeditor\models\EditTurn;
use spacecatninja\aiimageeditor\records\EditSession as EditSessionRecord;
use spacecatninja\aiimageeditor\records\EditTurn as EditTurnRecord;

use Throwable;
use yii\base\InvalidConfigException;

/**
 * The Sessions service manages edit sessions, their turns, and the temporary
 * files that hold intermediate results. Intermediate results are never Craft
 * assets, they live in the plugin's runtime storage until a session is
 * finalized or purged.
 *
 * An instance of the service is available via `AiImageEditor::$plugin->getSessions()`.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class SessionsService extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * @event EditEvent The event triggered before an edit turn runs against a
     * driver. Handlers may mutate the request or cancel the turn.
     * @since 1.0.0
     */
    public const EVENT_BEFORE_EDIT = 'beforeEdit';

    /**
     * @event EditEvent The event triggered after a successful edit turn.
     * @since 1.0.0
     */
    public const EVENT_AFTER_EDIT = 'afterEdit';

    /**
     * @var string[] File extensions that are excluded even though Craft treats them as images.
     */
    public const UNSUPPORTED_EXTENSIONS = ['svg'];

    /**
     * @var string Base directory (key prefix) for session files in the filesystem.
     */
    private const FS_BASE_DIR = 'ai-image-editor';

    // Private Properties
    // =========================================================================

    /**
     * @var FsInterface|null The filesystem holding session working images.
     * @see _fs()
     */
    private ?FsInterface $_fs = null;

    // Public Methods
    // =========================================================================

    /**
     * Creates a new edit session for an asset, and copies the asset's file
     * into the session's temp directory as the initial working image.
     *
     * @param Asset $asset
     * @param User  $user
     * @return EditSession
     * @throws SessionException if the asset is not a raster image, or its file can't be copied
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function createSession(Asset $asset, User $user): EditSession
    {
        if ($asset->kind !== Asset::KIND_IMAGE) {
            throw new SessionException(Craft::t('ai-image-editor', 'Only image assets can be edited.'));
        }

        $extension = strtolower($asset->getExtension());

        if (\in_array($extension, self::UNSUPPORTED_EXTENSIONS, true)) {
            throw new SessionException(Craft::t('ai-image-editor', 'Vector images can not be edited.'));
        }

        $settings = AiImageEditor::$plugin?->getSettings();

        $record = new EditSessionRecord();
        $record->sourceAssetId = $asset->id;
        $record->userId = $user->id;
        $record->driverHandle = $settings?->driver ?? '';
        $record->model = $this->_resolveSessionModel($record->driverHandle);
        $record->status = SessionStatus::Active->value;
        $record->save(false);

        $session = EditSession::fromRecord($record);

        try {
            $tempPath = $asset->getCopyOfFile();
            $stream = fopen($tempPath, 'rb');

            if ($stream === false) {
                throw new SessionException(Craft::t('ai-image-editor', 'The source image could not be read.'));
            }

            $this->_fs()->writeFileFromStream($this->_sessionPrefix($session) . '/source.' . $extension, $stream);

            if (\is_resource($stream)) {
                fclose($stream);
            }

            FileHelper::unlink($tempPath);
        } catch (Throwable $throwable) {
            $record->delete();
            $this->deleteSessionFiles($record->id);

            throw new SessionException(Craft::t('ai-image-editor', 'The source image could not be copied into the edit session.'), 0, $throwable);
        }

        return $session;
    }

    /**
     * Creates a new generation session: one without a source asset, whose
     * first turn generates a brand new image from the prompt alone, and whose
     * result is saved into the given folder.
     *
     * @param User $user
     * @param int  $folderId
     * @return EditSession
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function createGenerationSession(User $user, int $folderId): EditSession
    {
        $settings = AiImageEditor::$plugin?->getSettings();

        $record = new EditSessionRecord();
        $record->sourceAssetId = null;
        $record->targetFolderId = $folderId;
        $record->userId = $user->id;
        $record->driverHandle = $settings?->driver ?? '';
        $record->model = $this->_resolveSessionModel($record->driverHandle);
        $record->status = SessionStatus::Active->value;
        $record->save(false);

        return EditSession::fromRecord($record);
    }

    /**
     * Returns a session by its ID.
     *
     * @param int $id
     * @return EditSession|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSessionById(int $id): ?EditSession
    {
        $record = EditSessionRecord::findOne(['id' => $id]);

        return $record !== null ? EditSession::fromRecord($record) : null;
    }

    /**
     * Returns all turns for a session, oldest first.
     *
     * @param EditSession $session
     * @param bool        $includeFinal whether finalize-candidate turns should be included
     * @return EditTurn[]
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getTurns(EditSession $session, bool $includeFinal = true): array
    {
        $query = EditTurnRecord::find()
            ->where(['sessionId' => $session->id])
            ->orderBy(['id' => SORT_ASC]);

        if (!$includeFinal) {
            $query->andWhere(['isFinal' => false]);
        }

        /** @var EditTurnRecord[] $records */
        $records = $query->all();

        return array_map(
            static fn(EditTurnRecord $record) => EditTurn::fromRecord($record),
            $records
        );
    }

    /**
     * Returns the latest turn for a session. By default the latest working
     * turn is returned, pass `$isFinal = true` for the latest finalize candidate.
     *
     * @param EditSession $session
     * @param bool        $isFinal
     * @return EditTurn|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getLatestTurn(EditSession $session, bool $isFinal = false): ?EditTurn
    {
        /** @var EditTurnRecord|null $record */
        $record = EditTurnRecord::find()
            ->where(['sessionId' => $session->id, 'isFinal' => $isFinal])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $record !== null ? EditTurn::fromRecord($record) : null;
    }

    /**
     * Reverts a session to an earlier working turn, discarding every later
     * turn and any finalize candidate. The target turn becomes the session's
     * latest working turn, so subsequent edits, finalize and save all continue
     * from it. Finalize candidates are always cleared because they're
     * ephemeral and must be regenerated from the new working image.
     *
     * The target turn's provider interaction chain is also broken, so the next
     * turn resumes from its image rather than from stale provider state (see
     * {@see _breakProviderChain()}).
     *
     * @param EditSession $session
     * @param int         $turnId the working turn to revert to
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function revertToTurn(EditSession $session, int $turnId): void
    {
        $prefix = $this->_sessionPrefix($session);

        // Delete the image files of every turn being dropped.
        foreach ($this->getTurns($session) as $turn) {
            if ($turn->id <= $turnId && !$turn->isFinal) {
                continue;
            }

            try {
                $this->_fs()->deleteFile($prefix . '/' . $turn->resultPath);
            } catch (Throwable $throwable) {
                Craft::warning("Could not delete reverted turn file \"{$turn->resultPath}\": {$throwable->getMessage()}", __METHOD__);
            }
        }

        EditTurnRecord::deleteAll([
            'and',
            ['sessionId' => $session->id],
            ['or', ['>', 'id', $turnId], ['isFinal' => true]],
        ]);

        $this->_breakProviderChain($session, $turnId);
        $this->_touchSession($session);
    }

    /**
     * Reverts a session to its original source image, discarding every turn.
     * The session's working image becomes the source again, so the next edit
     * starts over from it. Only meaningful for edit sessions (a generation
     * session has no source image).
     *
     * @param EditSession $session
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function revertToSource(EditSession $session): void
    {
        $prefix = $this->_sessionPrefix($session);

        foreach ($this->getTurns($session) as $turn) {
            try {
                $this->_fs()->deleteFile($prefix . '/' . $turn->resultPath);
            } catch (Throwable $throwable) {
                Craft::warning("Could not delete reverted turn file \"{$turn->resultPath}\": {$throwable->getMessage()}", __METHOD__);
            }
        }

        EditTurnRecord::deleteAll(['sessionId' => $session->id]);

        $this->_touchSession($session);
    }

    /**
     * Runs one edit turn against the session's driver. On success the turn is
     * persisted and returned along with the driver result, on failure only the
     * result is returned so the UI can surface the error without losing state.
     *
     * @param EditSession $session
     * @param string      $prompt
     * @param string|null $resolution      resolution tier to request, defaults to the configured working resolution
     * @param bool        $isFinal         whether this turn is a finalize candidate
     * @param bool        $preserveContent whether the driver should append its content-preservation instructions
     * @param string|null $aspectRatio     requested output aspect ratio, null follows the working image
     * @param string|null $outputFormat    requested output format, null lets the driver use its default
     * @return array{result: \spacecatninja\aiimageeditor\models\EditResult, turn: EditTurn|null}
     * @throws SessionException if the session's working image no longer exists on disk
     * @throws InvalidConfigException if the session's driver is not registered
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function runTurn(EditSession $session, string $prompt, ?string $resolution = null, bool $isFinal = false, bool $preserveContent = true, ?string $aspectRatio = null, ?string $outputFormat = null): array
    {
        $driver = AiImageEditor::$plugin?->getDrivers()->getDriver($session->driverHandle);
        $latestTurn = $this->getLatestTurn($session);

        // Materialize the working image (the latest turn, else the source) from
        // the filesystem to a local file the driver can read. A generation
        // session's first turn legitimately has none.
        $localSource = $latestTurn !== null
            ? $this->getTurnImageLocalPath($session, $latestTurn)
            : $this->getSourceImageLocalPath($session);

        if ($localSource === null) {
            if (!$session->isGeneration() || $latestTurn !== null) {
                throw new SessionException(Craft::t('ai-image-editor', 'The working image for this session no longer exists. It may have been purged, please start a new session.'));
            }

            $localSource = '';
        }

        // The driver reads/writes plain local files; give it a scratch directory
        // that only has to survive this request.
        $targetDir = $this->_localScratchDir();

        try {
            $request = new EditRequest([
                'prompt' => $prompt,
                'preserveContent' => $preserveContent,
                'aspectRatio' => $aspectRatio,
                'outputFormat' => $outputFormat,
                'sourceImagePath' => $localSource,
                'resolution' => $resolution ?? $driver->getWorkingResolution($session->model),
                'model' => $session->model,
                'providerState' => $latestTurn?->providerMeta,
                'targetDir' => $targetDir,
            ]);

            if ($this->hasEventHandlers(self::EVENT_BEFORE_EDIT)) {
                $event = new EditEvent(['session' => $session, 'request' => $request]);
                $this->trigger(self::EVENT_BEFORE_EDIT, $event);

                // A handler may have swapped in a different request.
                $request = $event->request;

                if (!$event->isValid) {
                    $result = new EditResult();
                    $result->errorMessage = Craft::t('ai-image-editor', 'The edit was cancelled.');

                    return ['result' => $result, 'turn' => null];
                }
            }

            $result = $driver->edit($request);

            if (!$result->success || $result->resultImagePath === null) {
                return ['result' => $result, 'turn' => null];
            }

            // Persist the driver's local result into the (durable) filesystem,
            // keyed by its basename, which is what the turn record stores.
            $basename = basename($result->resultImagePath);
            $this->_storeFile($result->resultImagePath, $this->_sessionPrefix($session) . '/' . $basename);

            $turnRecord = new EditTurnRecord();
            $turnRecord->sessionId = $session->id;
            $turnRecord->prompt = $prompt;
            $turnRecord->resultPath = $basename;
            $turnRecord->resolution = $result->resolutionActual ?? $request->resolution;
            $turnRecord->isFinal = $isFinal;
            $turnRecord->providerMeta = Json::encode($result->providerMeta);
            $turnRecord->save(false);

            $this->_touchSession($session);

            $turn = EditTurn::fromRecord($turnRecord);

            if ($this->hasEventHandlers(self::EVENT_AFTER_EDIT)) {
                $this->trigger(self::EVENT_AFTER_EDIT, new EditEvent([
                    'session' => $session,
                    'request' => $request,
                    'result' => $result,
                ]));
            }

            return ['result' => $result, 'turn' => $turn];
        } finally {
            if ($localSource !== '') {
                FileHelper::unlink($localSource);
            }

            $this->_removeLocalDir($targetDir);
        }
    }

    /**
     * Updates the model used by a session, for subsequent turns and the
     * finalize step.
     *
     * @param EditSession $session
     * @param string      $model
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function updateSessionModel(EditSession $session, string $model): void
    {
        Craft::$app->getDb()->createCommand()
            ->update(Table::SESSIONS, ['model' => $model], ['id' => $session->id])
            ->execute();

        $session->model = $model;
    }

    /**
     * Discards a session, deleting its database rows and temporary files.
     *
     * @param EditSession $session
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function discardSession(EditSession $session): void
    {
        EditSessionRecord::deleteAll(['id' => $session->id]);
        $this->deleteSessionFiles($session->id);
    }

    /**
     * Marks a session as finalized, pointing it at the created asset, and
     * removes its temporary files.
     *
     * @param EditSession $session
     * @param int         $assetId
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function markFinalized(EditSession $session, int $assetId): void
    {
        Craft::$app->getDb()->createCommand()
            ->update(Table::SESSIONS, [
                'status' => SessionStatus::Finalized->value,
                'resultAssetId' => $assetId,
            ], ['id' => $session->id])
            ->execute();

        $this->deleteSessionFiles($session->id);
    }

    /**
     * Purges active sessions that haven't been touched within the configured
     * age threshold, along with their temporary files.
     *
     * @param int|null $hours age threshold, defaults to the `purgeSessionsAfterHours` setting
     * @return int the number of purged sessions
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function purgeStaleSessions(?int $hours = null): int
    {
        $settings = AiImageEditor::$plugin?->getSettings();
        $hours ??= $settings->purgeSessionsAfterHours ?? 48;
        $threshold = Db::prepareDateForDb(DateTimeHelper::now()->modify("-{$hours} hours"));

        /** @var EditSessionRecord[] $records */
        $records = EditSessionRecord::find()
            ->where(['status' => SessionStatus::Active->value])
            ->andWhere(['<', 'dateUpdated', $threshold])
            ->all();

        foreach ($records as $record) {
            $this->deleteSessionFiles($record->id);
            $record->delete();
        }

        return \count($records);
    }

    /**
     * Returns a local, readable copy of the session's source image, or null when
     * the session has no source (a generation session) or it no longer exists.
     * The caller owns the returned temp file and should delete it when done.
     *
     * @param EditSession $session
     * @return string|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSourceImageLocalPath(EditSession $session): ?string
    {
        $key = $this->_findSourceKey($session);

        return $key !== null ? $this->_materialize($key) : null;
    }

    /**
     * Returns a local, readable copy of a turn's result image, or null when it
     * no longer exists. The caller owns the returned temp file and should delete
     * it when done.
     *
     * @param EditSession $session
     * @param EditTurn    $turn
     * @return string|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getTurnImageLocalPath(EditSession $session, EditTurn $turn): ?string
    {
        return $this->_materialize($this->_sessionPrefix($session) . '/' . $turn->resultPath);
    }

    /**
     * Returns a read stream, filename and size for the session's source image,
     * or null when there is none. The caller must close the stream.
     *
     * @param EditSession $session
     * @return array{stream: resource, filename: string, fileSize: int}|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSourceImageStream(EditSession $session): ?array
    {
        $key = $this->_findSourceKey($session);

        return $key !== null ? $this->_stream($key) : null;
    }

    /**
     * Returns a read stream, filename and size for a turn's result image, or
     * null when it no longer exists. The caller must close the stream.
     *
     * @param EditSession $session
     * @param EditTurn    $turn
     * @return array{stream: resource, filename: string, fileSize: int}|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getTurnImageStream(EditSession $session, EditTurn $turn): ?array
    {
        return $this->_stream($this->_sessionPrefix($session) . '/' . $turn->resultPath);
    }

    /**
     * Deletes a session's working files from the filesystem.
     *
     * @param int $sessionId
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function deleteSessionFiles(int $sessionId): void
    {
        $path = self::FS_BASE_DIR . '/' . $sessionId;

        try {
            if ($this->_fs()->directoryExists($path)) {
                $this->_fs()->deleteDirectory($path);
            }
        } catch (Throwable $throwable) {
            Craft::warning("Could not remove session files \"{$path}\": {$throwable->getMessage()}", __METHOD__);
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Resolves the model a new session should use: the driver's configured
     * `defaultModel`, or its first available model when unset.
     */
    private function _resolveSessionModel(string $driverHandle): string
    {
        try {
            return AiImageEditor::$plugin?->getDrivers()->getDriver($driverHandle)->getDefaultModel() ?? '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Returns the filesystem used for session working images.
     *
     * On Craft Cloud this resolves to a private, object-storage-backed temp
     * filesystem that is not registered as an asset volume filesystem, so
     * nothing written here is ever picked up by a volume re-index; locally it's
     * an on-disk temp filesystem. Must be created through the container
     * (`Craft::createObject`) so Cloud's `Temp` override applies.
     */
    private function _fs(): FsInterface
    {
        if ($this->_fs === null) {
            /** @var FsInterface $fs */
            $fs = Craft::createObject(Temp::class);
            $this->_fs = $fs;
        }

        return $this->_fs;
    }

    /**
     * Returns the filesystem key prefix for a session's working files.
     */
    private function _sessionPrefix(EditSession $session): string
    {
        return self::FS_BASE_DIR . '/' . $session->id;
    }

    /**
     * Finds the filesystem key of a session's source image, whose extension
     * follows the original asset. Returns null for generation sessions, or when
     * the file no longer exists.
     */
    private function _findSourceKey(EditSession $session): ?string
    {
        $prefix = $this->_sessionPrefix($session);

        try {
            foreach ($this->_fs()->getFileList($prefix, false) as $listing) {
                if (!$listing->getIsDir() && str_starts_with($listing->getBasename(), 'source.')) {
                    return $prefix . '/' . $listing->getBasename();
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Copies a filesystem file to a local temp file and returns its path, or
     * null when the file doesn't exist. The caller owns the temp file.
     */
    private function _materialize(string $key): ?string
    {
        $fs = $this->_fs();

        if (!$fs->fileExists($key)) {
            return null;
        }

        $localPath = AssetsHelper::tempFilePath(pathinfo($key, PATHINFO_EXTENSION) ?: 'png');
        $in = $fs->getFileStream($key);
        $out = fopen($localPath, 'wb');

        if ($out === false) {
            if (\is_resource($in)) {
                fclose($in);
            }

            return null;
        }

        stream_copy_to_stream($in, $out);
        fclose($out);

        if (\is_resource($in)) {
            fclose($in);
        }

        return $localPath;
    }

    /**
     * Returns a read stream, filename and size for a filesystem file, or null
     * when it doesn't exist.
     *
     * @return array{stream: resource, filename: string, fileSize: int}|null
     */
    private function _stream(string $key): ?array
    {
        $fs = $this->_fs();

        if (!$fs->fileExists($key)) {
            return null;
        }

        return [
            'stream' => $fs->getFileStream($key),
            'filename' => basename($key),
            'fileSize' => $fs->getFileSize($key),
        ];
    }

    /**
     * Copies a local file into the filesystem at the given key.
     *
     * @throws SessionException if the local file can't be read
     */
    private function _storeFile(string $localPath, string $key): void
    {
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new SessionException(Craft::t('ai-image-editor', 'The result image could not be read back for storage.'));
        }

        try {
            $this->_fs()->writeFileFromStream($key, $stream);
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Creates a fresh local scratch directory for one turn's driver I/O. It only
     * has to survive the current request, so the system temp path is fine.
     */
    private function _localScratchDir(): string
    {
        $dir = Craft::$app->getPath()->getTempPath() . '/' . self::FS_BASE_DIR . '/' . StringHelper::UUID();
        FileHelper::createDirectory($dir);

        return $dir;
    }

    /**
     * Best-effort removal of a local scratch directory.
     */
    private function _removeLocalDir(string $dir): void
    {
        try {
            FileHelper::removeDirectory($dir);
        } catch (Throwable) {
            // Scratch cleanup is best-effort.
        }
    }

    /**
     * Updates the session's dateUpdated timestamp, so the purge threshold is
     * measured from the last activity rather than from creation.
     */
    private function _touchSession(EditSession $session): void
    {
        Craft::$app->getDb()->createCommand()
            ->update(Table::SESSIONS, [
                'dateUpdated' => Db::prepareDateForDb(DateTimeHelper::now()),
            ], ['id' => $session->id])
            ->execute();
    }

    /**
     * Clears the stored provider interaction id on the turn a session was just
     * reverted to.
     *
     * Stateful providers (e.g. Gemini's interaction chaining) keep the whole
     * chain server-side, and the dropped turns are not removed there. Continuing
     * from this turn's interaction would therefore resume from a non-leaf node,
     * which the provider does not reliably fork from that turn's output. Clearing
     * the interaction id forces the next turn to run statelessly, re-uploading
     * this turn's image as the basis and starting a fresh chain from it. Any
     * other provider metadata (model, usage) is left intact for logs.
     */
    private function _breakProviderChain(EditSession $session, int $turnId): void
    {
        /** @var EditTurnRecord|null $record */
        $record = EditTurnRecord::findOne([
            'sessionId' => $session->id,
            'id' => $turnId,
            'isFinal' => false,
        ]);

        if ($record === null || $record->providerMeta === null) {
            return;
        }

        $meta = Json::decodeIfJson($record->providerMeta);

        if (!\is_array($meta) || !isset($meta['interactionId'])) {
            return;
        }

        unset($meta['interactionId']);
        $record->providerMeta = $meta === [] ? null : Json::encode($meta);
        $record->save(false);
    }
}
