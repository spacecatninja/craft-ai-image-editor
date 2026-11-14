<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Services;

use CraftCms\Cms\Asset\AssetsHelper;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Enums\FileKind;
use CraftCms\Cms\Support\Facades\Assets;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use SpaceCatNinja\AiImageEditor\Data\EditRequest;
use SpaceCatNinja\AiImageEditor\Data\EditResult;
use SpaceCatNinja\AiImageEditor\Data\EditSession;
use SpaceCatNinja\AiImageEditor\Data\EditTurn;
use SpaceCatNinja\AiImageEditor\Enums\SessionStatus;
use SpaceCatNinja\AiImageEditor\Events\EditingImage;
use SpaceCatNinja\AiImageEditor\Events\ImageEdited;
use SpaceCatNinja\AiImageEditor\Exceptions\EditDriverException;
use SpaceCatNinja\AiImageEditor\Exceptions\SessionException;
use SpaceCatNinja\AiImageEditor\Models\EditSession as EditSessionRecord;
use SpaceCatNinja\AiImageEditor\Models\EditTurn as EditTurnRecord;
use SpaceCatNinja\AiImageEditor\Settings;
use Throwable;

use function CraftCms\Cms\t;

/**
 * The Sessions service manages edit sessions, their turns, and the temporary
 * files that hold intermediate results. Intermediate results are never Craft
 * assets, they live in the plugin's runtime storage until a session is
 * finalized or purged.
 *
 * Resolve it from the container: `app(SessionsService::class)`.
 *
 * @author André Elvan
 * @since 1.0.0
 */
#[Singleton]
class SessionsService
{
    // Const Properties
    // =========================================================================

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

    public function __construct(
        private readonly DriversService $drivers,
    ) {
    }

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
        if ($asset->kind !== FileKind::Image->value) {
            throw new SessionException(t('Only image assets can be edited.', category: 'ai-image-editor'));
        }

        $extension = strtolower($asset->getExtension());

        if (\in_array($extension, self::UNSUPPORTED_EXTENSIONS, true)) {
            throw new SessionException(t('Vector images can not be edited.', category: 'ai-image-editor'));
        }

        $record = new EditSessionRecord();
        $record->sourceAssetId = $asset->id;
        $record->userId = $user->id;
        $record->driverHandle = app(Settings::class)->driver;
        $record->model = $this->_resolveSessionModel($record->driverHandle);
        $record->status = SessionStatus::Active->value;
        $record->save();

        $session = EditSession::fromRecord($record);

        try {
            $tempPath = $asset->getCopyOfFile();
            $stream = fopen($tempPath, 'rb');

            if ($stream === false) {
                throw new SessionException(t('The source image could not be read.', category: 'ai-image-editor'));
            }

            $this->disk()->writeStream($this->_sessionPrefix($session) . '/source.' . $extension, $stream);

            if (\is_resource($stream)) {
                fclose($stream);
            }

            File::delete($tempPath);
        } catch (Throwable $throwable) {
            $record->delete();
            $this->deleteSessionFiles($record->id);

            throw new SessionException(t('The source image could not be copied into the edit session.', category: 'ai-image-editor'), 0, $throwable);
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
        $record = new EditSessionRecord();
        $record->sourceAssetId = null;
        $record->targetFolderId = $folderId;
        $record->userId = $user->id;
        $record->driverHandle = app(Settings::class)->driver;
        $record->model = $this->_resolveSessionModel($record->driverHandle);
        $record->status = SessionStatus::Active->value;
        $record->save();

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
        $record = EditSessionRecord::find($id);

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
        return EditTurnRecord::query()
            ->where('sessionId', $session->id)
            ->when(!$includeFinal, fn($query) => $query->where('isFinal', false))
            ->orderBy('id')
            ->get()
            ->map(static fn(EditTurnRecord $record) => EditTurn::fromRecord($record))
            ->all();
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
        $record = EditTurnRecord::query()
            ->where('sessionId', $session->id)
            ->where('isFinal', $isFinal)
            ->orderByDesc('id')
            ->first();

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
                $this->disk()->delete($prefix . '/' . $turn->resultPath);
            } catch (Throwable $throwable) {
                Log::warning("Could not delete reverted turn file \"{$turn->resultPath}\": {$throwable->getMessage()}");
            }
        }

        EditTurnRecord::query()
            ->where('sessionId', $session->id)
            ->where(fn($query) => $query->where('id', '>', $turnId)->orWhere('isFinal', true))
            ->delete();

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
                $this->disk()->delete($prefix . '/' . $turn->resultPath);
            } catch (Throwable $throwable) {
                Log::warning("Could not delete reverted turn file \"{$turn->resultPath}\": {$throwable->getMessage()}");
            }
        }

        EditTurnRecord::query()->where('sessionId', $session->id)->delete();

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
     * @return array{result: \SpaceCatNinja\AiImageEditor\Data\EditResult, turn: EditTurn|null}
     * @throws SessionException if the session's working image no longer exists on disk
     * @throws EditDriverException if the session's driver is not registered
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function runTurn(EditSession $session, string $prompt, ?string $resolution = null, bool $isFinal = false, bool $preserveContent = true, ?string $aspectRatio = null, ?string $outputFormat = null): array
    {
        $driver = $this->drivers->getDriver($session->driverHandle);
        $latestTurn = $this->getLatestTurn($session);

        // Materialize the working image (the latest turn, else the source) from
        // the filesystem to a local file the driver can read. A generation
        // session's first turn legitimately has none.
        $localSource = $latestTurn !== null
            ? $this->getTurnImageLocalPath($session, $latestTurn)
            : $this->getSourceImageLocalPath($session);

        if ($localSource === null) {
            if (!$session->isGeneration() || $latestTurn !== null) {
                throw new SessionException(t('The working image for this session no longer exists. It may have been purged, please start a new session.', category: 'ai-image-editor'));
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

            event($event = new EditingImage($session, $request));

            // A listener may have swapped in a different request.
            $request = $event->request;

            if (!$event->isValid) {
                $result = new EditResult();
                $result->errorMessage = t('The edit was cancelled.', category: 'ai-image-editor');

                return ['result' => $result, 'turn' => null];
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
            $turnRecord->save();

            $this->_touchSession($session);

            $turn = EditTurn::fromRecord($turnRecord);

            event(new ImageEdited($session, $request, $result));

            return ['result' => $result, 'turn' => $turn];
        } finally {
            if ($localSource !== '') {
                File::delete($localSource);
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
        EditSessionRecord::query()->whereKey($session->id)->update(['model' => $model]);

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
        EditSessionRecord::query()->whereKey($session->id)->delete();
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
        EditSessionRecord::query()->whereKey($session->id)->update([
            'status' => SessionStatus::Finalized->value,
            'resultAssetId' => $assetId,
        ]);

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
        $hours ??= app(Settings::class)->purgeSessionsAfterHours;

        $records = EditSessionRecord::query()
            ->where('status', SessionStatus::Active->value)
            ->where('dateUpdated', '<', now()->subHours($hours))
            ->get();

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
            if ($this->disk()->directoryExists($path)) {
                $this->disk()->deleteDirectory($path);
            }
        } catch (Throwable $throwable) {
            Log::warning("Could not remove session files \"{$path}\": {$throwable->getMessage()}");
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
            return $this->drivers->getDriver($driverHandle)->getDefaultModel() ?? '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Returns the disk used for session working images: Craft's temporary asset
     * upload disk (the `tempAssetUploadDisk` general config setting).
     *
     * It isn't an asset volume, so nothing written here is ever picked up by a
     * volume re-index. Locally it's an on-disk temp directory; hosts with an
     * ephemeral filesystem point it at object storage, so session files survive
     * between requests served by different instances.
     */
    private function disk(): FilesystemAdapter
    {
        return Assets::getTempAssetUploadDisk();
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
            foreach ($this->disk()->files($prefix) as $path) {
                if (str_starts_with(basename($path), 'source.')) {
                    return $path;
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
        $disk = $this->disk();

        if (!$disk->fileExists($key)) {
            return null;
        }

        $in = $disk->readStream($key);

        if ($in === null) {
            return null;
        }

        $localPath = AssetsHelper::tempFilePath(pathinfo($key, PATHINFO_EXTENSION) ?: 'png');
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
        $disk = $this->disk();

        if (!$disk->fileExists($key)) {
            return null;
        }

        $stream = $disk->readStream($key);

        if ($stream === null) {
            return null;
        }

        return [
            'stream' => $stream,
            'filename' => basename($key),
            'fileSize' => $disk->size($key),
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
            throw new SessionException(t('The result image could not be read back for storage.', category: 'ai-image-editor'));
        }

        try {
            $this->disk()->writeStream($key, $stream);
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
        $dir = Path::temp(self::FS_BASE_DIR . '/' . Str::uuid()->toString());

        return $dir;
    }

    /**
     * Best-effort removal of a local scratch directory.
     */
    private function _removeLocalDir(string $dir): void
    {
        try {
            File::deleteDirectory($dir);
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
        EditSessionRecord::query()->whereKey($session->id)->update(['dateUpdated' => now()]);
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
        $record = EditTurnRecord::query()
            ->where('sessionId', $session->id)
            ->whereKey($turnId)
            ->where('isFinal', false)
            ->first();

        if ($record === null || $record->providerMeta === null) {
            return;
        }

        $meta = Json::decodeIfJson($record->providerMeta);

        if (!\is_array($meta) || !isset($meta['interactionId'])) {
            return;
        }

        unset($meta['interactionId']);
        $record->providerMeta = $meta === [] ? null : Json::encode($meta);
        $record->save();
    }
}
