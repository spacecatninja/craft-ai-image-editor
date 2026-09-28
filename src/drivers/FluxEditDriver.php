<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\drivers;

use Craft;

use spacecatninja\aiimageeditor\clients\FluxApiClient;
use spacecatninja\aiimageeditor\exceptions\EditDriverException;
use spacecatninja\aiimageeditor\exceptions\FluxApiException;
use spacecatninja\aiimageeditor\models\EditRequest;
use spacecatninja\aiimageeditor\models\EditResult;

use yii\base\ErrorException;

/**
 * Edit driver for the Black Forest Labs FLUX.2 image models, which do both
 * editing and text-to-image generation, up to ~4MP.
 *
 * FLUX has no vision model, so the analysis tasks (focal point detection,
 * descriptive filenames) return null here, pair it with the `analysisDriver`
 * setting to route those to a provider that has one. Turns are stateless, and
 * output resolution is requested as explicit pixel dimensions.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class FluxEditDriver extends BaseEditDriver
{
    // Const Properties
    // =========================================================================

    public const HANDLE = 'flux';

    /**
     * @var string The default model.
     */
    public const DEFAULT_MODEL = 'flux-2-max';

    /**
     * @var string[] Aspect ratios offered for selection. FLUX supports a
     * continuous range, this is a sensible discrete subset for the UI.
     */
    private const ASPECT_RATIOS = ['1:1', '3:2', '2:3', '4:3', '3:4', '16:9', '9:16'];

    /**
     * @var array<string, string> The models offered for selection.
     */
    private const MODELS = [
        'flux-2-max' => 'FLUX.2 [max]',
        'flux-2-pro' => 'FLUX.2 [pro]',
        'flux-2-flex' => 'FLUX.2 [flex]',
        'flux-2-klein-9b' => 'FLUX.2 [klein] 9B',
    ];

    /**
     * @var array<string, int> Target pixel budget per resolution tier. FLUX.2
     * output tops out around 4MP; dimensions are derived from these and the
     * requested aspect ratio.
     */
    private const TIER_PIXELS = [
        '1K' => 1024 * 1024,
        '2K' => 2048 * 2048,
    ];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getHandle(): string
    {
        return self::HANDLE;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Black Forest Labs (FLUX)';
    }

    /**
     * @inheritdoc
     */
    public function getAvailableModels(): array
    {
        return self::MODELS;
    }

    /**
     * @inheritdoc
     */
    protected function nativeResolutions(?string $model = null): array
    {
        return ['1K', '2K'];
    }

    /**
     * @inheritdoc
     */
    public function getSupportedAspectRatios(?string $model = null): array
    {
        return self::ASPECT_RATIOS;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedOutputFormats(?string $model = null): array
    {
        return ['png', 'jpeg', 'webp'];
    }

    /**
     * @inheritdoc
     */
    public function testConnection(): bool
    {
        $this->_createClient()->ping();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function edit(EditRequest $request): EditResult
    {
        $result = new EditResult();

        try {
            $client = $this->_createClient();
        } catch (EditDriverException $editDriverException) {
            $result->errorMessage = $editDriverException->getMessage();

            return $result;
        }

        $isGeneration = $request->sourceImagePath === '';

        if (!$isGeneration && !file_exists($request->sourceImagePath)) {
            $result->errorMessage = 'The source image for this edit could not be found.';
            Craft::error("Source image not found at \"{$request->sourceImagePath}\".", __METHOD__);

            return $result;
        }

        $model = $this->_resolveModel($request->model);
        $tier = $this->_clampResolution($request->resolution);
        [$width, $height] = $this->_resolveDimensions($request, $isGeneration, $tier);

        $payload = [
            'prompt' => $this->buildPrompt($request),
            'output_format' => $this->resolveOutputFormat($request),
            'safety_tolerance' => min(5, max(0, (int)$this->getConfig('safetyTolerance', 2))),
            'width' => $width,
            'height' => $height,
        ];

        // Editing attaches the working image; generation runs on the prompt alone.
        if (!$isGeneration) {
            $payload['input_image'] = base64_encode(file_get_contents($request->sourceImagePath));
        }

        try {
            $image = $client->submitAndAwait($model, $payload);
        } catch (FluxApiException $fluxApiException) {
            $result->isRefusal = $fluxApiException->isRefusal;
            $result->errorMessage = $fluxApiException->getMessage();
            $result->providerMeta = [
                'statusCode' => $fluxApiException->statusCode,
                'retryAfter' => $fluxApiException->retryAfter,
            ];
            Craft::error("FLUX edit request failed: {$fluxApiException->getMessage()}", __METHOD__);

            return $result;
        }

        try {
            $resultImagePath = $this->writeResultImage($request->targetDir, $image['data']);
        } catch (EditDriverException|ErrorException $writeException) {
            $result->errorMessage = 'The edited image could not be saved to disk.';
            Craft::error("Could not write FLUX result image: {$writeException->getMessage()}", __METHOD__);

            return $result;
        }

        $result->success = true;
        $result->resultImagePath = $resultImagePath;
        $result->mimeType = $image['mimeType'];
        $result->resolutionActual = $tier;
        $result->providerMeta = ['model' => $model];

        return $result;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function analyzeImage(string $imagePath, string $prompt): ?string
    {
        // FLUX has no vision model; analysis is handled by the analysisDriver.
        return null;
    }

    // Private Methods
    // =========================================================================

    /**
     * Creates an API client with the configured credentials.
     *
     * @throws EditDriverException if no API key is configured
     */
    private function _createClient(): FluxApiClient
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === null) {
            throw new EditDriverException('The FLUX API key is not configured. Set it under `driverConfig.flux.apiKey` in `config/ai-image-editor.php`, typically referencing a `FLUX_API_KEY` environment variable.');
        }

        return new FluxApiClient($apiKey, $this->getRequestTimeout());
    }

    /**
     * Resolves the model to use, falling back to the driver's default.
     */
    private function _resolveModel(?string $model): string
    {
        $model = $model ?? $this->getDefaultModel();

        return isset(self::MODELS[$model]) ? $model : self::DEFAULT_MODEL;
    }

    /**
     * Clamps a requested resolution tier to what the driver supports, falling
     * back to the highest supported tier (so the global `finalResolution`
     * default of `4K` resolves to the FLUX maximum).
     */
    private function _clampResolution(string $resolution): string
    {
        $supported = $this->getSupportedResolutions();

        if (\in_array($resolution, $supported, true)) {
            return $resolution;
        }

        $last = end($supported);

        return $last !== false ? $last : '1K';
    }

    /**
     * Resolves the output pixel dimensions for a request: the target pixel
     * budget for the tier, distributed across the requested aspect ratio (or
     * the source image's ratio when matching the original), rounded to
     * multiples of 32 and floored at the API minimum.
     *
     * @return array{0: int, 1: int} the width and height
     */
    private function _resolveDimensions(EditRequest $request, bool $isGeneration, string $tier): array
    {
        $targetPixels = self::TIER_PIXELS[$tier] ?? self::TIER_PIXELS['1K'];
        [$ratioWidth, $ratioHeight] = $this->_resolveRatio($request, $isGeneration);

        $scale = sqrt($targetPixels / ($ratioWidth * $ratioHeight));
        $width = max(64, (int)round($ratioWidth * $scale / 32) * 32);
        $height = max(64, (int)round($ratioHeight * $scale / 32) * 32);

        // Rounding each side up to a multiple of 32 can push the total over the
        // tier's pixel budget, which FLUX rejects (width * height must not
        // exceed the tier maximum). Trim the longer side by 32 until it fits.
        while ($width * $height > $targetPixels) {
            if ($width >= $height && $width > 64) {
                $width -= 32;
            } elseif ($height > 64) {
                $height -= 32;
            } else {
                break;
            }
        }

        return [$width, $height];
    }

    /**
     * Resolves the aspect ratio to target: an explicitly requested ratio wins,
     * otherwise the source image's ratio is matched for edits, falling back to
     * square for generation or unreadable images.
     *
     * @return array{0: float, 1: float} the width and height ratio parts
     */
    private function _resolveRatio(EditRequest $request, bool $isGeneration): array
    {
        if ($request->aspectRatio !== null && preg_match('/^(\d+):(\d+)$/', $request->aspectRatio, $matches)) {
            return [(float)$matches[1], (float)$matches[2]];
        }

        if (!$isGeneration) {
            $size = @getimagesize($request->sourceImagePath);

            if ($size !== false && !empty($size[0]) && !empty($size[1])) {
                return [(float)$size[0], (float)$size[1]];
            }
        }

        return [1.0, 1.0];
    }
}
