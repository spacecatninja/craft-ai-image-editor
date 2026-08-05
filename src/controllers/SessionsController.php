<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\controllers;

use Craft;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use spacecatninja\aiimageeditor\AiImageEditor;
use spacecatninja\aiimageeditor\enums\SessionStatus;
use spacecatninja\aiimageeditor\exceptions\SessionException;
use spacecatninja\aiimageeditor\models\EditSession;
use spacecatninja\aiimageeditor\models\EditTurn;

use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Handles the editor's session lifecycle: create, run turns, fetch state,
 * finalize, confirm, discard, and stream turn preview images.
 *
 * All actions are control panel AJAX endpoints. Edit turns run the provider
 * call synchronously, but the response contract is designed so the backend
 * can move to queue-plus-polling later without changing the frontend.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class SessionsController extends Controller
{
    // Const Properties
    // =========================================================================

    /**
     * @var string The permission required to edit existing images with AI.
     * @since 1.0.0
     */
    public const PERMISSION_EDIT = 'ai-image-editor:edit';

    /**
     * @var string The permission required to generate new images with AI.
     * @since 1.0.0
     */
    public const PERMISSION_GENERATE = 'ai-image-editor:generate';

    // Protected Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();

        return true;
    }

    /**
     * Creates a new session: an edit session when an asset ID is posted, or a
     * generation session when a target folder ID is posted instead.
     *
     * @return Response
     * @throws BadRequestHttpException if neither an asset ID nor a folder ID was posted
     * @throws NotFoundHttpException if the asset or folder doesn't exist
     * @throws ForbiddenHttpException if the user can't view the asset, or can't save assets in the folder's volume
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionCreate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $assetId = (int)$this->request->getBodyParam('assetId', 0);
        $folderId = (int)$this->request->getBodyParam('folderId', 0);

        if ($assetId === 0 && $folderId === 0) {
            throw new BadRequestHttpException('Either an asset ID or a folder ID is required.');
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            throw new ForbiddenHttpException('You must be logged in.');
        }

        $plugin = AiImageEditor::$plugin;
        $settings = $plugin->getSettings();

        try {
            $driver = $plugin->getDrivers()->getDriver($settings->driver);
        } catch (Throwable $throwable) {
            Craft::error("Could not create edit driver: {$throwable->getMessage()}", __METHOD__);

            return $this->asJson([
                'success' => false,
                'code' => 'not-configured',
                'message' => Craft::t('ai-image-editor', 'The configured edit driver could not be created. Check `config/ai-image-editor.php`.'),
            ]);
        }

        if (!$driver->isConfigured()) {
            return $this->asJson([
                'success' => false,
                'code' => 'not-configured',
                'message' => Craft::t('ai-image-editor', '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.', ['driver' => $driver->getName()]),
            ]);
        }

        if ($assetId !== 0) {
            $this->requirePermission(self::PERMISSION_EDIT);

            $asset = Craft::$app->getAssets()->getAssetById($assetId);

            if (!$asset) {
                throw new NotFoundHttpException('Asset not found.');
            }

            if (!Craft::$app->getElements()->canView($asset, $currentUser)) {
                throw new ForbiddenHttpException('You don\'t have permission to view this asset.');
            }

            try {
                $session = $plugin->getSessions()->createSession($asset, $currentUser);
            } catch (SessionException $sessionException) {
                return $this->asJson([
                    'success' => false,
                    'code' => 'invalid-asset',
                    'message' => $sessionException->getMessage(),
                ]);
            }
        } else {
            $this->requirePermission(self::PERMISSION_GENERATE);

            $folder = Craft::$app->getAssets()->getFolderById($folderId);

            if (!$folder) {
                throw new NotFoundHttpException('Folder not found.');
            }

            $volume = $folder->getVolume();

            if (!$currentUser->can("saveAssets:{$volume->uid}")) {
                throw new ForbiddenHttpException('You don\'t have permission to save assets in this volume.');
            }

            $session = $plugin->getSessions()->createGenerationSession($currentUser, $folderId);
        }

        return $this->asJson([
            'success' => true,
            'session' => $this->_sessionPayload($session),
        ]);
    }

    /**
     * Runs one edit turn.
     *
     * @return Response
     * @throws BadRequestHttpException if required params are missing
     * @throws NotFoundHttpException if the session doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionTurn(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $session = $this->_requireSession((int)$this->request->getRequiredBodyParam('sessionId'));

        if (($rateLimited = $this->_checkRateLimit()) !== null) {
            return $rateLimited;
        }

        $prompt = trim((string)$this->request->getRequiredBodyParam('prompt'));
        $preserveContent = (bool)$this->request->getBodyParam('precise', true);
        $aspectRatio = $this->_validAspectRatio($session, (string)$this->request->getBodyParam('aspectRatio', ''));
        $outputFormat = $this->_validOutputFormat($session, (string)$this->request->getBodyParam('outputFormat', ''));

        $this->_maybeUpdateSessionModel($session, trim((string)$this->request->getBodyParam('model', '')));

        if ($session->status !== SessionStatus::Active) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('ai-image-editor', 'This session is no longer active.'),
            ]);
        }

        if ($prompt === '') {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('ai-image-editor', 'Please enter an instruction.'),
            ]);
        }

        try {
            ['result' => $result, 'turn' => $turn] = AiImageEditor::$plugin->getSessions()->runTurn(
                $session,
                $prompt,
                preserveContent: $preserveContent,
                aspectRatio: $aspectRatio,
                outputFormat: $outputFormat,
            );
        } catch (Throwable $throwable) {
            Craft::error("Edit turn failed: {$throwable->getMessage()}", __METHOD__);

            return $this->asJson([
                'success' => false,
                'message' => $throwable instanceof SessionException
                    ? $throwable->getMessage()
                    : Craft::t('ai-image-editor', 'The edit could not be performed. Please try again.'),
            ]);
        }

        if (!$result->success || $turn === null) {
            return $this->asJson([
                'success' => false,
                'refusal' => $result->isRefusal,
                'message' => $result->errorMessage,
                'retryAfter' => $result->providerMeta['retryAfter'] ?? null,
            ]);
        }

        return $this->asJson([
            'success' => true,
            'turn' => $this->_turnPayload($session, $turn),
        ]);
    }

    /**
     * Returns a session and its turn history, for reloading UI state.
     *
     * @return Response
     * @throws NotFoundHttpException if the session doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionGet(): Response
    {
        $this->requireAcceptsJson();

        $session = $this->_requireSession((int)$this->request->getRequiredQueryParam('sessionId'));

        return $this->asJson([
            'success' => true,
            'session' => $this->_sessionPayload($session),
        ]);
    }

    /**
     * Reverts the session to an earlier working turn, discarding the drafts
     * made after it, and returns the refreshed session so the UI can rehydrate
     * its history strip.
     *
     * @return Response
     * @throws NotFoundHttpException if the session doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user
     * @throws BadRequestHttpException if the turn doesn't belong to the session or is a finalize candidate
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionRevert(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $session = $this->_requireSession((int)$this->request->getRequiredBodyParam('sessionId'));
        $turnId = (string)$this->request->getRequiredBodyParam('turnId');

        if ($turnId === 'source') {
            // Revert to the original, discarding every edit and starting over.
            if ($session->isGeneration()) {
                throw new BadRequestHttpException('A generated image has no original to revert to.');
            }

            AiImageEditor::$plugin->getSessions()->revertToSource($session);
        } else {
            $turn = $this->_getSessionTurn($session, (int)$turnId);

            // Finalize candidates aren't part of the working history; you revert to a draft.
            if ($turn->isFinal) {
                throw new BadRequestHttpException('Cannot revert to a finalize candidate.');
            }

            AiImageEditor::$plugin->getSessions()->revertToTurn($session, (int)$turn->id);
        }

        return $this->asJson([
            'success' => true,
            'session' => $this->_sessionPayload($session),
        ]);
    }

    /**
     * Generates the finalize candidate: the last accepted result regenerated
     * at the configured final resolution, for before/after comparison.
     *
     * @return Response
     * @throws NotFoundHttpException if the session doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionFinalize(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $session = $this->_requireSession((int)$this->request->getRequiredBodyParam('sessionId'));

        if (($rateLimited = $this->_checkRateLimit()) !== null) {
            return $rateLimited;
        }

        $this->_maybeUpdateSessionModel($session, trim((string)$this->request->getBodyParam('model', '')));
        $resolution = $this->_validResolution($session, trim((string)$this->request->getBodyParam('finalResolution', '')));
        $outputFormat = $this->_validOutputFormat($session, (string)$this->request->getBodyParam('outputFormat', ''));

        try {
            ['result' => $result, 'turn' => $turn] = AiImageEditor::$plugin->getFinalize()->createFinalCandidate($session, $resolution, $outputFormat);
        } catch (Throwable $throwable) {
            Craft::error("Finalize failed: {$throwable->getMessage()}", __METHOD__);

            return $this->asJson([
                'success' => false,
                'canFallback' => true,
                'message' => $throwable instanceof SessionException
                    ? $throwable->getMessage()
                    : Craft::t('ai-image-editor', 'The high resolution version could not be generated.'),
            ]);
        }

        if (!$result->success || $turn === null) {
            return $this->asJson([
                'success' => false,
                'canFallback' => true,
                'refusal' => $result->isRefusal,
                'message' => $result->errorMessage,
            ]);
        }

        $before = AiImageEditor::$plugin->getSessions()->getLatestTurn($session);

        return $this->asJson([
            'success' => true,
            'before' => $before !== null ? $this->_turnPayload($session, $before) : null,
            'after' => $this->_turnPayload($session, $turn),
        ]);
    }

    /**
     * Saves the session's result, either as a new asset or by replacing the
     * source asset's file when `replaceOriginal` is passed. Pass
     * `useWorkingResolution` to save the last working resolution result
     * instead of the finalize candidate, the fallback when the high
     * resolution regeneration failed or drifted.
     *
     * @return Response
     * @throws NotFoundHttpException if the session doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user, or the user lacks the volume permission for the chosen save mode
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionConfirm(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $session = $this->_requireSession((int)$this->request->getRequiredBodyParam('sessionId'));
        $useWorkingResolution = (bool)$this->request->getBodyParam('useWorkingResolution', false);
        $replaceOriginal = (bool)$this->request->getBodyParam('replaceOriginal', false);

        if ($session->isGeneration() && $replaceOriginal) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('ai-image-editor', 'A generated image has no original to replace.'),
            ]);
        }

        $sourceAsset = $session->getSourceAsset();
        $currentUser = Craft::$app->getUser()->getIdentity();

        // An edit session whose source asset was deleted has no volume to check
        // against, so reject it here rather than letting the permission checks
        // below fall through to the save.
        if (!$session->isGeneration() && $sourceAsset === null) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('ai-image-editor', 'The original asset for this session no longer exists.'),
            ]);
        }

        if ($session->isGeneration() && $currentUser !== null) {
            $folder = Craft::$app->getAssets()->getFolderById((int)$session->targetFolderId);

            if ($folder !== null && !$currentUser->can("saveAssets:{$folder->getVolume()->uid}")) {
                throw new ForbiddenHttpException('You don\'t have permission to save assets in this volume.');
            }
        } elseif ($sourceAsset !== null && $currentUser !== null) {
            $volume = $sourceAsset->getVolume();

            if ($replaceOriginal) {
                if (!$currentUser->can("replaceFiles:{$volume->uid}")) {
                    throw new ForbiddenHttpException('You don\'t have permission to replace files in this volume.');
                }

                if ((int)$sourceAsset->uploaderId !== (int)$currentUser->id && !$currentUser->can("replacePeerFiles:{$volume->uid}")) {
                    throw new ForbiddenHttpException('You don\'t have permission to replace files uploaded by other users.');
                }
            } elseif (!$currentUser->can("saveAssets:{$volume->uid}")) {
                throw new ForbiddenHttpException('You don\'t have permission to save assets in this volume.');
            }
        }

        try {
            $asset = AiImageEditor::$plugin->getFinalize()->saveResult(
                $session,
                useWorkingResolution: $useWorkingResolution,
                replaceOriginal: $replaceOriginal,
            );
        } catch (SessionException $sessionException) {
            return $this->asJson([
                'success' => false,
                'message' => $sessionException->getMessage(),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'replaced' => $replaceOriginal,
            'asset' => [
                'id' => $asset->id,
                'filename' => $asset->getFilename(),
                'cpEditUrl' => $asset->getCpEditUrl(),
            ],
        ]);
    }

    /**
     * Discards a session, deleting its data and temporary files.
     *
     * @return Response
     * @throws NotFoundHttpException if the session doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionDiscard(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $session = $this->_requireSession((int)$this->request->getRequiredBodyParam('sessionId'));
        AiImageEditor::$plugin->getSessions()->discardSession($session);

        return $this->asSuccess();
    }

    /**
     * Streams a turn's result image, or the session's source image when
     * `turnId` is `source`.
     *
     * @return Response
     * @throws BadRequestHttpException if the turn doesn't belong to the session
     * @throws NotFoundHttpException if the session, turn or image file doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionTurnImage(): Response
    {
        $session = $this->_requireSession((int)$this->request->getRequiredQueryParam('sessionId'));
        $turnId = $this->request->getRequiredQueryParam('turnId');
        $sessions = AiImageEditor::$plugin->getSessions();

        $image = null;

        try {
            if ($turnId === 'source') {
                $image = $sessions->getSourceImageStream($session);
            } else {
                $turn = $this->_getSessionTurn($session, (int)$turnId);
                $image = $sessions->getTurnImageStream($session, $turn);
            }
        } catch (BadRequestHttpException $badRequestException) {
            // An invalid turn ID stays a 400.
            throw $badRequestException;
        } catch (Throwable $throwable) {
            // A filesystem read error becomes a handled 404, not a raw 500.
            Craft::error("Could not read the session image: {$throwable->getMessage()}", __METHOD__);
        }

        if ($image === null) {
            throw new NotFoundHttpException('Image not found.');
        }

        return $this->response->sendStreamAsFile($image['stream'], $image['filename'], [
            'inline' => true,
            'fileSize' => $image['fileSize'],
            'mimeType' => FileHelper::getMimeTypeByExtension($image['filename']) ?? 'application/octet-stream',
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Enforces the per-user request throttle for the billable actions (turn and
     * finalize). Returns a JSON error response when the current user is over the
     * `maxRequestsPerMinute` cap, or null when the request may proceed (also when
     * the cap is disabled with `0`).
     *
     * Uses a fixed one-minute cache window keyed by user ID, so a runaway retry
     * loop or a scripted client can't run up provider costs unchecked.
     */
    private function _checkRateLimit(): ?Response
    {
        $limit = AiImageEditor::$plugin->getSettings()->maxRequestsPerMinute;

        if ($limit <= 0) {
            return null;
        }

        $userId = Craft::$app->getUser()->getId();

        if ($userId === null) {
            return null;
        }

        $window = 60;
        $cache = Craft::$app->getCache();
        $key = "ai-image-editor:rate:{$userId}:" . (int)floor(time() / $window);
        $count = (int)$cache->get($key);

        if ($count >= $limit) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('ai-image-editor', 'You are making requests too quickly. Please wait a moment and try again.'),
                'retryAfter' => $window - (time() % $window),
            ]);
        }

        $cache->set($key, $count + 1, $window);

        return null;
    }

    /**
     * Returns the session with the given ID, ensuring it exists and belongs to
     * the current user.
     *
     * @throws NotFoundHttpException if the session doesn't exist
     * @throws ForbiddenHttpException if the session belongs to another user
     */
    private function _requireSession(int $sessionId): EditSession
    {
        $session = AiImageEditor::$plugin->getSessions()->getSessionById($sessionId);

        if ($session === null) {
            throw new NotFoundHttpException('Session not found.');
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser === null || $session->userId !== (int)$currentUser->id) {
            throw new ForbiddenHttpException('This edit session belongs to another user.');
        }

        $this->requirePermission($session->isGeneration() ? self::PERMISSION_GENERATE : self::PERMISSION_EDIT);

        return $session;
    }

    /**
     * Returns a turn, ensuring it belongs to the given session.
     *
     * @throws BadRequestHttpException if the turn doesn't belong to the session
     */
    private function _getSessionTurn(EditSession $session, int $turnId): EditTurn
    {
        $turns = AiImageEditor::$plugin->getSessions()->getTurns($session);

        foreach ($turns as $turn) {
            if ($turn->id === $turnId) {
                return $turn;
            }
        }

        throw new BadRequestHttpException('Invalid turn ID.');
    }

    /**
     * Builds the JSON payload for a session.
     */
    private function _sessionPayload(EditSession $session): array
    {
        $plugin = AiImageEditor::$plugin;
        $settings = $plugin->getSettings();
        $sourceAsset = $session->getSourceAsset();

        $driverName = $session->driverHandle;
        $aspectRatios = [];
        $outputFormats = [];
        $workingResolution = $settings->workingResolution;
        $finalResolution = $settings->finalResolution;
        $models = [];

        try {
            $driver = $plugin->getDrivers()->getDriver($session->driverHandle);
            $driverName = $driver->getName();
            $aspectRatios = $driver->getSupportedAspectRatios($session->model);
            $outputFormats = $driver->getSupportedOutputFormats($session->model);
            $workingResolution = $driver->getWorkingResolution($session->model);
            $finalResolution = $driver->getFinalResolution($session->model);

            foreach ($driver->getAvailableModels() as $value => $label) {
                $models[] = [
                    'value' => $value,
                    'label' => $label,
                    'resolutions' => $driver->getSupportedResolutions($value),
                ];
            }

            // A raw model ID from the config still shows up as a choice.
            if ($session->model !== '' && !\in_array($session->model, array_column($models, 'value'), true)) {
                $models[] = [
                    'value' => $session->model,
                    'label' => $session->model,
                    'resolutions' => $driver->getSupportedResolutions($session->model),
                ];
            }
        } catch (Throwable) {
            // Leave the handle as the display name if the driver is gone.
        }

        // When a separate analysis driver is configured, images are also sent
        // to that provider (focal point, filenames), so disclose both.
        $providerNames = [$driverName];

        try {
            $analysisDriver = $plugin->getDrivers()->getAnalysisDriver($session->driverHandle);

            if ($analysisDriver->getHandle() !== $session->driverHandle) {
                $analysisName = $analysisDriver->getName();

                if (!\in_array($analysisName, $providerNames, true)) {
                    $providerNames[] = $analysisName;
                }
            }
        } catch (Throwable) {
            // If the analysis driver can't be resolved, list only the edit provider.
        }

        // Quick-action presets, keeping only well-formed entries.
        $presets = [];

        foreach ($settings->presets as $preset) {
            if (!\is_array($preset)) {
                continue;
            }

            $label = trim((string)($preset['label'] ?? ''));
            $prompt = trim((string)($preset['prompt'] ?? ''));

            if ($label === '' || $prompt === '') {
                continue;
            }

            $presets[] = [
                'label' => $label,
                'prompt' => $prompt,
                'precise' => isset($preset['precise']) ? (bool)$preset['precise'] : null,
            ];
        }

        // The output format defaults to the source image's format when the
        // driver can produce it, so saving or replacing keeps the format.
        $defaultOutputFormat = null;

        if ($outputFormats !== []) {
            $sourceFormat = null;

            if ($sourceAsset !== null) {
                $extension = strtolower($sourceAsset->getExtension());
                $sourceFormat = $extension === 'jpg' ? 'jpeg' : $extension;
            }

            $defaultOutputFormat = ($sourceFormat !== null && \in_array($sourceFormat, $outputFormats, true))
                ? $sourceFormat
                : $outputFormats[0];
        }

        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'isGeneration' => $session->isGeneration(),
            'driverHandle' => $session->driverHandle,
            'driverName' => $driverName,
            'providerNames' => $providerNames,
            'model' => $session->model,
            'models' => $models,
            'workingResolution' => $workingResolution,
            'finalResolution' => $finalResolution,
            'aspectRatios' => $aspectRatios,
            'outputFormats' => $outputFormats,
            'defaultOutputFormat' => $defaultOutputFormat,
            'presets' => $presets,
            'sourceAsset' => $sourceAsset !== null ? [
                'id' => $sourceAsset->id,
                'filename' => $sourceAsset->getFilename(),
                'width' => $sourceAsset->getWidth(),
                'height' => $sourceAsset->getHeight(),
            ] : null,
            'sourceImageUrl' => $session->isGeneration() ? null : $this->_imageUrl($session, 'source'),
            'turns' => array_map(
                fn(EditTurn $turn) => $this->_turnPayload($session, $turn),
                $plugin->getSessions()->getTurns($session)
            ),
        ];
    }

    /**
     * Builds the JSON payload for a turn.
     */
    private function _turnPayload(EditSession $session, EditTurn $turn): array
    {
        return [
            'id' => $turn->id,
            'prompt' => $turn->prompt,
            'resolution' => $turn->resolution,
            'isFinal' => $turn->isFinal,
            'dateCreated' => $turn->dateCreated?->format('c'),
            'imageUrl' => $this->_imageUrl($session, (string)$turn->id),
        ];
    }

    /**
     * Switches the session to another of the driver's available models.
     * Unknown or empty values are ignored.
     */
    private function _maybeUpdateSessionModel(EditSession $session, string $model): void
    {
        if ($model === '' || $model === $session->model) {
            return;
        }

        try {
            $driver = AiImageEditor::$plugin->getDrivers()->getDriver($session->driverHandle);
        } catch (Throwable) {
            return;
        }

        if (!\array_key_exists($model, $driver->getAvailableModels())) {
            return;
        }

        AiImageEditor::$plugin->getSessions()->updateSessionModel($session, $model);
    }

    /**
     * Returns the given resolution tier if the session's driver supports it
     * for the session's model, or null to use the configured default.
     */
    private function _validResolution(EditSession $session, string $resolution): ?string
    {
        if ($resolution === '') {
            return null;
        }

        try {
            $supported = AiImageEditor::$plugin->getDrivers()
                ->getDriver($session->driverHandle)
                ->getSupportedResolutions($session->model);
        } catch (Throwable) {
            return null;
        }

        return \in_array($resolution, $supported, true) ? $resolution : null;
    }

    /**
     * Returns the given aspect ratio if the session's driver supports it, or
     * null so the output follows the working image.
     */
    private function _validAspectRatio(EditSession $session, string $aspectRatio): ?string
    {
        if ($aspectRatio === '') {
            return null;
        }

        try {
            $supported = AiImageEditor::$plugin->getDrivers()
                ->getDriver($session->driverHandle)
                ->getSupportedAspectRatios($session->model);
        } catch (Throwable) {
            return null;
        }

        return \in_array($aspectRatio, $supported, true) ? $aspectRatio : null;
    }

    /**
     * Returns the given output format if the session's driver supports it, or
     * null so the driver uses its default.
     */
    private function _validOutputFormat(EditSession $session, string $outputFormat): ?string
    {
        if ($outputFormat === '') {
            return null;
        }

        try {
            $supported = AiImageEditor::$plugin->getDrivers()
                ->getDriver($session->driverHandle)
                ->getSupportedOutputFormats($session->model);
        } catch (Throwable) {
            return null;
        }

        return \in_array($outputFormat, $supported, true) ? $outputFormat : null;
    }

    /**
     * Builds the URL for streaming a session image.
     */
    private function _imageUrl(EditSession $session, string $turnId): string
    {
        return UrlHelper::actionUrl('ai-image-editor/sessions/turn-image', [
            'sessionId' => $session->id,
            'turnId' => $turnId,
        ]);
    }
}
