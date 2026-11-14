<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Http\Controllers;

use CraftCms\Cms\Support\Facades\Assets;
use CraftCms\Cms\Support\Facades\Folders;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use SpaceCatNinja\AiImageEditor\AiImageEditor;
use SpaceCatNinja\AiImageEditor\Data\EditSession;
use SpaceCatNinja\AiImageEditor\Data\EditTurn;
use SpaceCatNinja\AiImageEditor\Enums\SessionStatus;
use SpaceCatNinja\AiImageEditor\Exceptions\SessionException;
use SpaceCatNinja\AiImageEditor\Services\DriversService;
use SpaceCatNinja\AiImageEditor\Services\FinalizeService;
use SpaceCatNinja\AiImageEditor\Services\SessionsService;
use SpaceCatNinja\AiImageEditor\Settings;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Handles the editor's session lifecycle: create, run turns, fetch state,
 * finalize, confirm, discard, and stream turn preview images.
 *
 * All actions are control panel JSON endpoints, routed in `routes/cp.php`, so
 * they require a logged-in user with control panel access. Edit turns run the provider
 * call synchronously, but the response contract is designed so the backend
 * can move to queue-plus-polling later without changing the frontend.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class SessionsController
{
    public function __construct(
        private readonly SessionsService $sessions,
        private readonly FinalizeService $finalize,
        private readonly DriversService $drivers,
    ) {
    }

    // Public Methods
    // =========================================================================

    /**
     * Creates a new session: an edit session when an asset ID is posted, or a
     * generation session when a target folder ID is posted instead.
     *
     * Responds with a 400 if neither an asset ID nor a folder ID was posted, a
     * 404 if the asset or folder doesn't exist, and a 403 if the user can't view
     * the asset, or can't save assets in the folder's volume.
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function create(Request $request): JsonResponse
    {
        $assetId = $request->integer('assetId');
        $folderId = $request->integer('folderId');

        abort_if($assetId === 0 && $folderId === 0, 400, 'Either an asset ID or a folder ID is required.');

        $currentUser = $this->currentUser();

        try {
            $driver = $this->drivers->getDriver(app(Settings::class)->driver);
        } catch (Throwable $throwable) {
            Log::error("Could not create edit driver: {$throwable->getMessage()}");

            return response()->json([
                'success' => false,
                'code' => 'not-configured',
                'message' => t('The configured edit driver could not be created. Check `config/ai-image-editor.php`.', category: 'ai-image-editor'),
            ]);
        }

        if (!$driver->isConfigured()) {
            return response()->json([
                'success' => false,
                'code' => 'not-configured',
                'message' => t('{driver} is not configured. Add your API key in `config/ai-image-editor.php`.', ['driver' => $driver->getName()], 'ai-image-editor'),
            ]);
        }

        if ($assetId !== 0) {
            Gate::authorize(AiImageEditor::PERMISSION_EDIT);

            $asset = Assets::getAssetById($assetId);

            abort_if($asset === null, 404, 'Asset not found.');
            abort_unless(Gate::check('view', $asset), 403, 'You don\'t have permission to view this asset.');

            try {
                $session = $this->sessions->createSession($asset, $currentUser);
            } catch (SessionException $sessionException) {
                return response()->json([
                    'success' => false,
                    'code' => 'invalid-asset',
                    'message' => $sessionException->getMessage(),
                ]);
            }
        } else {
            Gate::authorize(AiImageEditor::PERMISSION_GENERATE);

            $folder = Folders::getFolderById($folderId);

            abort_if($folder === null, 404, 'Folder not found.');
            abort_unless(Gate::check("saveAssets:{$folder->getVolume()->uid}"), 403, 'You don\'t have permission to save assets in this volume.');

            $session = $this->sessions->createGenerationSession($currentUser, $folderId);
        }

        return response()->json([
            'success' => true,
            'session' => $this->_sessionPayload($session),
        ]);
    }

    /**
     * Runs one edit turn.
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function turn(Request $request): JsonResponse
    {
        $request->validate([
            'sessionId' => ['required', 'integer'],
            'prompt' => ['present', 'nullable', 'string'],
        ]);

        $session = $this->_requireSession($request->integer('sessionId'));

        if (($rateLimited = $this->_checkRateLimit()) !== null) {
            return $rateLimited;
        }

        $prompt = trim($request->string('prompt')->toString());
        $preserveContent = $request->boolean('precise', true);
        $aspectRatio = $this->_validAspectRatio($session, $request->string('aspectRatio')->toString());
        $outputFormat = $this->_validOutputFormat($session, $request->string('outputFormat')->toString());

        $this->_maybeUpdateSessionModel($session, trim($request->string('model')->toString()));

        if ($session->status !== SessionStatus::Active) {
            return response()->json([
                'success' => false,
                'message' => t('This session is no longer active.', category: 'ai-image-editor'),
            ]);
        }

        if ($prompt === '') {
            return response()->json([
                'success' => false,
                'message' => t('Please enter an instruction.', category: 'ai-image-editor'),
            ]);
        }

        try {
            ['result' => $result, 'turn' => $turn] = $this->sessions->runTurn(
                $session,
                $prompt,
                preserveContent: $preserveContent,
                aspectRatio: $aspectRatio,
                outputFormat: $outputFormat,
            );
        } catch (Throwable $throwable) {
            Log::error("Edit turn failed: {$throwable->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => $throwable instanceof SessionException
                    ? $throwable->getMessage()
                    : t('The edit could not be performed. Please try again.', category: 'ai-image-editor'),
            ]);
        }

        if (!$result->success || $turn === null) {
            return response()->json([
                'success' => false,
                'refusal' => $result->isRefusal,
                'message' => $result->errorMessage,
                'retryAfter' => $result->providerMeta['retryAfter'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'turn' => $this->_turnPayload($session, $turn),
        ]);
    }

    /**
     * Returns a session and its turn history, for reloading UI state.
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function get(Request $request): JsonResponse
    {
        $request->validate(['sessionId' => ['required', 'integer']]);

        $session = $this->_requireSession($request->integer('sessionId'));

        return response()->json([
            'success' => true,
            'session' => $this->_sessionPayload($session),
        ]);
    }

    /**
     * Reverts the session to an earlier working turn, discarding the drafts
     * made after it, and returns the refreshed session so the UI can rehydrate
     * its history strip.
     *
     * Responds with a 400 if the turn doesn't belong to the session or is a
     * finalize candidate.
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function revert(Request $request): JsonResponse
    {
        $request->validate([
            'sessionId' => ['required', 'integer'],
            'turnId' => ['required'],
        ]);

        $session = $this->_requireSession($request->integer('sessionId'));
        $turnId = $request->string('turnId')->toString();

        if ($turnId === 'source') {
            // Revert to the original, discarding every edit and starting over.
            abort_if($session->isGeneration(), 400, 'A generated image has no original to revert to.');

            $this->sessions->revertToSource($session);
        } else {
            $turn = $this->_getSessionTurn($session, (int)$turnId);

            // Finalize candidates aren't part of the working history; you revert to a draft.
            abort_if($turn->isFinal, 400, 'Cannot revert to a finalize candidate.');

            $this->sessions->revertToTurn($session, (int)$turn->id);
        }

        return response()->json([
            'success' => true,
            'session' => $this->_sessionPayload($session),
        ]);
    }

    /**
     * Generates the finalize candidate: the last accepted result regenerated
     * at the configured final resolution, for before/after comparison.
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function finalize(Request $request): JsonResponse
    {
        $request->validate(['sessionId' => ['required', 'integer']]);

        $session = $this->_requireSession($request->integer('sessionId'));

        if (($rateLimited = $this->_checkRateLimit()) !== null) {
            return $rateLimited;
        }

        $this->_maybeUpdateSessionModel($session, trim($request->string('model')->toString()));
        $resolution = $this->_validResolution($session, trim($request->string('finalResolution')->toString()));
        $outputFormat = $this->_validOutputFormat($session, $request->string('outputFormat')->toString());

        try {
            ['result' => $result, 'turn' => $turn] = $this->finalize->createFinalCandidate($session, $resolution, $outputFormat);
        } catch (Throwable $throwable) {
            Log::error("Finalize failed: {$throwable->getMessage()}");

            return response()->json([
                'success' => false,
                'canFallback' => true,
                'message' => $throwable instanceof SessionException
                    ? $throwable->getMessage()
                    : t('The high resolution version could not be generated.', category: 'ai-image-editor'),
            ]);
        }

        if (!$result->success || $turn === null) {
            return response()->json([
                'success' => false,
                'canFallback' => true,
                'refusal' => $result->isRefusal,
                'message' => $result->errorMessage,
            ]);
        }

        $before = $this->sessions->getLatestTurn($session);

        return response()->json([
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
     * Responds with a 403 if the user lacks the volume permission for the
     * chosen save mode.
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['sessionId' => ['required', 'integer']]);

        $session = $this->_requireSession($request->integer('sessionId'));
        $useWorkingResolution = $request->boolean('useWorkingResolution');
        $replaceOriginal = $request->boolean('replaceOriginal');

        if ($session->isGeneration() && $replaceOriginal) {
            return response()->json([
                'success' => false,
                'message' => t('A generated image has no original to replace.', category: 'ai-image-editor'),
            ]);
        }

        $sourceAsset = $session->getSourceAsset();
        $currentUser = $this->currentUser();

        // An edit session whose source asset was deleted has no volume to check
        // against, so reject it here rather than letting the permission checks
        // below fall through to the save.
        if (!$session->isGeneration() && $sourceAsset === null) {
            return response()->json([
                'success' => false,
                'message' => t('The original asset for this session no longer exists.', category: 'ai-image-editor'),
            ]);
        }

        if ($session->isGeneration()) {
            $folder = Folders::getFolderById((int)$session->targetFolderId);

            abort_if(
                $folder !== null && !Gate::check("saveAssets:{$folder->getVolume()->uid}"),
                403,
                'You don\'t have permission to save assets in this volume.',
            );
        } elseif ($sourceAsset !== null) {
            $volume = $sourceAsset->getVolume();

            if ($replaceOriginal) {
                abort_unless(Gate::check("replaceFiles:{$volume->uid}"), 403, 'You don\'t have permission to replace files in this volume.');
                abort_if(
                    (int)$sourceAsset->uploaderId !== (int)$currentUser->id && !Gate::check("replacePeerFiles:{$volume->uid}"),
                    403,
                    'You don\'t have permission to replace files uploaded by other users.',
                );
            } else {
                abort_unless(Gate::check("saveAssets:{$volume->uid}"), 403, 'You don\'t have permission to save assets in this volume.');
            }
        }

        try {
            $asset = $this->finalize->saveResult(
                $session,
                useWorkingResolution: $useWorkingResolution,
                replaceOriginal: $replaceOriginal,
            );
        } catch (SessionException $sessionException) {
            return response()->json([
                'success' => false,
                'message' => $sessionException->getMessage(),
            ]);
        }

        return response()->json([
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
     * @author André Elvan
     * @since 1.0.0
     */
    public function discard(Request $request): JsonResponse
    {
        $request->validate(['sessionId' => ['required', 'integer']]);

        $session = $this->_requireSession($request->integer('sessionId'));
        $this->sessions->discardSession($session);

        return response()->json(['success' => true]);
    }

    /**
     * Streams a turn's result image, or the session's source image when
     * `turnId` is `source`.
     *
     * Responds with a 400 if the turn doesn't belong to the session, and a 404
     * if the image file doesn't exist.
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function turnImage(Request $request): StreamedResponse
    {
        $request->validate([
            'sessionId' => ['required', 'integer'],
            'turnId' => ['required'],
        ]);

        $session = $this->_requireSession($request->integer('sessionId'));
        $turnId = $request->string('turnId')->toString();

        // An invalid turn ID is a 400, so it's resolved outside the try.
        $turn = $turnId === 'source' ? null : $this->_getSessionTurn($session, (int)$turnId);
        $image = null;

        try {
            $image = $turn === null
                ? $this->sessions->getSourceImageStream($session)
                : $this->sessions->getTurnImageStream($session, $turn);
        } catch (Throwable $throwable) {
            // A filesystem read error becomes a handled 404, not a raw 500.
            Log::error("Could not read the session image: {$throwable->getMessage()}");
        }

        abort_if($image === null, 404, 'Image not found.');

        $stream = $image['stream'];

        return response()->stream(static function() use ($stream): void {
            fpassthru($stream);

            if (\is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => File::getMimeTypeByExtension($image['filename']) ?? 'application/octet-stream',
            'Content-Length' => (string)$image['fileSize'],
            'Content-Disposition' => sprintf('inline; filename="%s"', addcslashes($image['filename'], '"\\')),
            'Cache-Control' => 'private, max-age=3600',
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
     * Uses a one-minute rate limiter window keyed by user ID, so a runaway retry
     * loop or a scripted client can't run up provider costs unchecked.
     */
    private function _checkRateLimit(): ?JsonResponse
    {
        $limit = app(Settings::class)->maxRequestsPerMinute;

        if ($limit <= 0) {
            return null;
        }

        $key = 'ai-image-editor:rate:' . $this->currentUser()->id;

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json([
                'success' => false,
                'message' => t('You are making requests too quickly. Please wait a moment and try again.', category: 'ai-image-editor'),
                'retryAfter' => RateLimiter::availableIn($key),
            ]);
        }

        RateLimiter::hit($key, 60);

        return null;
    }

    /**
     * Returns the session with the given ID, ensuring it exists and belongs to
     * the current user.
     *
     * Responds with a 404 if the session doesn't exist, and a 403 if it
     * belongs to another user.
     */
    private function _requireSession(int $sessionId): EditSession
    {
        $session = $this->sessions->getSessionById($sessionId);

        abort_if($session === null, 404, 'Session not found.');
        abort_if($session->userId !== (int)$this->currentUser()->id, 403, 'This edit session belongs to another user.');

        Gate::authorize($session->isGeneration() ? AiImageEditor::PERMISSION_GENERATE : AiImageEditor::PERMISSION_EDIT);

        return $session;
    }

    /**
     * Returns the logged-in user. The routes require authentication, so there always is one.
     */
    private function currentUser(): User
    {
        $user = currentUserElement();

        abort_if($user === null, 403, 'You must be logged in.');

        return $user;
    }

    /**
     * Returns a turn, ensuring it belongs to the given session.
     *
     * Responds with a 400 if the turn doesn't belong to the session.
     */
    private function _getSessionTurn(EditSession $session, int $turnId): EditTurn
    {
        $turns = $this->sessions->getTurns($session);

        foreach ($turns as $turn) {
            if ($turn->id === $turnId) {
                return $turn;
            }
        }

        abort(400, 'Invalid turn ID.');
    }

    /**
     * Builds the JSON payload for a session.
     */
    private function _sessionPayload(EditSession $session): array
    {
        $settings = app(Settings::class);
        $sourceAsset = $session->getSourceAsset();

        $driverName = $session->driverHandle;
        $aspectRatios = [];
        $outputFormats = [];
        $workingResolution = $settings->workingResolution;
        $finalResolution = $settings->finalResolution;
        $models = [];

        try {
            $driver = $this->drivers->getDriver($session->driverHandle);
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
            $analysisDriver = $this->drivers->getAnalysisDriver($session->driverHandle);

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
                $this->sessions->getTurns($session)
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
            $driver = $this->drivers->getDriver($session->driverHandle);
        } catch (Throwable) {
            return;
        }

        if (!\array_key_exists($model, $driver->getAvailableModels())) {
            return;
        }

        $this->sessions->updateSessionModel($session, $model);
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
            $supported = $this->drivers
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
            $supported = $this->drivers
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
            $supported = $this->drivers
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
        return Url::cpUrl('ai-image-editor/sessions/turn-image', [
            'sessionId' => $session->id,
            'turnId' => $turnId,
        ]);
    }
}
