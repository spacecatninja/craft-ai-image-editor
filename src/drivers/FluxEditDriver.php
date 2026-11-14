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
 * Edit driver for the Black Forest Labs FLUX 3 Image and FLUX.2 models, which
 * do both editing and text-to-image generation, up to ~4MP.
 *
 * FLUX has no vision model, so the analysis tasks (focal point detection,
 * descriptive filenames) return null here, pair it with the `analysisDriver`
 * setting to route those to a provider that has one. Turns are stateless, and
 * output resolution is requested as explicit pixel dimensions. The FLUX 3
 * endpoints take a different request shape than FLUX.2 (an `images` list, a
 * wider safety range, and no unknown fields), see {@see _buildPayload()}.
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
    public const DEFAULT_MODEL = 'flux-3-image';

    /**
     * @var string[] Aspect ratios offered for selection. FLUX supports a
     * continuous range, this is a sensible discrete subset for the UI.
     */
    private const ASPECT_RATIOS = ['1:1', '3:2', '2:3', '4:3', '3:4', '16:9', '9:16'];

    /**
     * @var array<string, string> The models offered for selection.
     */
    private const MODELS = [
        'flux-3-image' => 'FLUX 3 Image',
        'flux-3-image-klein' => 'FLUX 3 Image Klein',
        'flux-2-max' => 'FLUX.2 [max]',
        'flux-2-pro' => 'FLUX.2 [pro]',
        'flux-2-flex' => 'FLUX.2 [flex]',
        'flux-2-klein-9b' => 'FLUX.2 [klein] 9B',
    ];

    /**
     * @var string[] The FLUX 3 models, which take reference images as an
     * `images` list instead of FLUX.2's `input_image`.
     */
    private const FLUX3_MODELS = ['flux-3-image', 'flux-3-image-klein'];

    /**
     * @var string[] Models with the `grounding` option (web and image search
     * while rendering). The API rejects it on every other model.
     */
    private const GROUNDING_MODELS = ['flux-3-image'];

    /**
     * @var array<string, string[]> Output formats per model, where they differ
     * from {@see DEFAULT_OUTPUT_FORMATS}. FLUX 3 Image has no `output_format`
     * parameter (the API picks the format and rejects the field), and FLUX 3
     * Image Klein has no WebP.
     */
    private const MODEL_OUTPUT_FORMATS = [
        'flux-3-image' => [],
        'flux-3-image-klein' => ['png', 'jpeg'],
    ];

    /**
     * @var string[] Output formats the FLUX.2 models support.
     */
    private const DEFAULT_OUTPUT_FORMATS = ['png', 'jpeg', 'webp'];

    /**
     * @var int The highest `safety_tolerance` the FLUX 3 models accept. FLUX.2
     * tops out at {@see FLUX2_MAX_SAFETY_TOLERANCE}.
     */
    private const FLUX3_MAX_SAFETY_TOLERANCE = 7;

    /**
     * @var int The highest `safety_tolerance` the FLUX.2 models accept.
     */
    private const FLUX2_MAX_SAFETY_TOLERANCE = 5;

    /**
     * @var array<string, int> Target pixel budget per resolution tier. FLUX
     * edits top out around 4MP; dimensions are derived from these and the
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
        return self::MODEL_OUTPUT_FORMATS[$this->_resolveModel($model)] ?? self::DEFAULT_OUTPUT_FORMATS;
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
        $payload = $this->_buildPayload($request, $model, $isGeneration, $width, $height);

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
        $model = $this->replaceRetiredModel($model ?? $this->getDefaultModel());

        return isset(self::MODELS[$model]) ? $model : self::DEFAULT_MODEL;
    }

    /**
     * Builds the request body for one turn. Every FLUX endpoint rejects
     * fields it doesn't know, so the optional ones are only added for the
     * models that accept them.
     */
    private function _buildPayload(EditRequest $request, string $model, bool $isGeneration, int $width, int $height): array
    {
        $isFlux3 = \in_array($model, self::FLUX3_MODELS, true);
        $maxSafetyTolerance = $isFlux3 ? self::FLUX3_MAX_SAFETY_TOLERANCE : self::FLUX2_MAX_SAFETY_TOLERANCE;

        $payload = [
            'prompt' => $this->buildPrompt($request),
            'safety_tolerance' => min($maxSafetyTolerance, max(0, (int)$this->getConfig('safetyTolerance', 2))),
            'width' => $width,
            'height' => $height,
        ];

        $outputFormat = $this->resolveOutputFormat($request, $model);

        if ($outputFormat !== null) {
            $payload['output_format'] = $outputFormat;
        }

        // Grounding searches the web for the prompt, so it's off unless asked for.
        if (\in_array($model, self::GROUNDING_MODELS, true)) {
            $payload['grounding'] = (bool)$this->getConfig('grounding', false);
        }

        // Editing attaches the working image; generation runs on the prompt alone.
        if ($isGeneration) {
            return $payload;
        }

        $image = base64_encode(file_get_contents($request->sourceImagePath));

        if ($isFlux3) {
            $payload['images'] = [$image];
        } else {
            $payload['input_image'] = $image;
        }

        return $payload;
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
