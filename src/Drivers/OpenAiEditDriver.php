<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Drivers;


use SpaceCatNinja\AiImageEditor\Clients\OpenAiApiClient;
use SpaceCatNinja\AiImageEditor\Exceptions\EditDriverException;
use SpaceCatNinja\AiImageEditor\Exceptions\OpenAiApiException;
use SpaceCatNinja\AiImageEditor\Data\EditRequest;
use SpaceCatNinja\AiImageEditor\Data\EditResult;

use CraftCms\Cms\Support\File;
use Illuminate\Support\Facades\Log;

/**
 * Edit driver for the OpenAI image models (GPT Image).
 *
 * Unlike the Gemini driver this one is stateless: OpenAI's Images API has no
 * multi-turn state, so every turn sends the current working image. Output
 * sizes are fixed per aspect ratio: the GPT Image 2.5 and 2.6 models reach 2K,
 * so both a `1K` and a `2K` tier are exposed for them and finalize regenerates
 * at the higher tier. GPT Image 2 tops out around 1.5K, where the single `1K`
 * tier makes the finalize step a plain save.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class OpenAiEditDriver extends BaseEditDriver
{
    // Const Properties
    // =========================================================================

    public const HANDLE = 'openai';

    /**
     * @var string The default model used for analysis tasks like focal point
     * detection. Can be overridden with the `analysisModel` driver config.
     */
    public const DEFAULT_ANALYSIS_MODEL = 'gpt-6.1-terra';

    /**
     * @var string The default model used for edits.
     */
    public const DEFAULT_MODEL = 'gpt-image-2.6-flare';

    /**
     * @var array<string, string> Models OpenAI has shut down, mapped to their
     * replacement.
     */
    protected const RETIRED_MODELS = [
        'gpt-image-1' => self::DEFAULT_MODEL,
        'gpt-image-1.5' => self::DEFAULT_MODEL,
        'gpt-image-1.5-2025-12-16' => self::DEFAULT_MODEL,
        'gpt-image-1-mini' => self::DEFAULT_MODEL,
    ];

    /**
     * @var array<string, array<string, string>> Output sizes the API accepts,
     * per resolution tier and aspect ratio. The 2K sizes are multiples of 16,
     * stay within the API's pixel-count bounds, and keep every edge under
     * 2560px, above which the API treats a size as experimental.
     */
    private const ASPECT_RATIO_SIZES = [
        '1K' => [
            '1:1' => '1024x1024',
            '3:2' => '1536x1024',
            '2:3' => '1024x1536',
        ],
        '2K' => [
            '1:1' => '2048x2048',
            '3:2' => '2496x1664',
            '2:3' => '1664x2496',
        ],
    ];

    /**
     * @var array<string, string> The models offered for selection.
     */
    private const MODELS = [
        'gpt-image-2.6-flare' => 'GPT Image 2.6 Flare',
        'gpt-image-2.6-sunburst' => 'GPT Image 2.6 Sunburst',
        'gpt-image-2.5-flare' => 'GPT Image 2.5 Flare',
        'gpt-image-2.5-sunburst' => 'GPT Image 2.5 Sunburst',
        'gpt-image-2' => 'GPT Image 2',
    ];

    /**
     * @var string[] Models that reach 2K and accept the `xhigh` and `max`
     * quality levels.
     */
    private const LARGE_OUTPUT_MODELS = [
        'gpt-image-2.6-flare',
        'gpt-image-2.6-sunburst',
        'gpt-image-2.5-flare',
        'gpt-image-2.5-sunburst',
    ];

    /**
     * @var string[] Quality levels only the 2.5 and 2.6 models accept. They
     * are clamped to `high` on the older ones, which the API would reject.
     */
    private const LARGE_OUTPUT_QUALITIES = ['xhigh', 'max'];

    /**
     * @var string[] Models that accept the `complexity` parameter, which sets
     * how much the model plans before rendering.
     */
    private const COMPLEXITY_MODELS = ['gpt-image-2.6-flare', 'gpt-image-2.6-sunburst'];

    /**
     * @var string The complexity used for edits with "Precise edits" on. OpenAI
     * recommends low complexity for focused edits, since higher levels can
     * introduce changes that weren't asked for.
     */
    private const PRECISE_COMPLEXITY = 'low';

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
        return 'OpenAI';
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
        return \in_array($this->_resolveModel($model), self::LARGE_OUTPUT_MODELS, true) ? ['1K', '2K'] : ['1K'];
    }

    /**
     * @inheritdoc
     */
    public function getSupportedAspectRatios(?string $model = null): array
    {
        // Every tier offers the same ratios, so the 1K table speaks for all of them
        return array_keys(self::ASPECT_RATIO_SIZES['1K']);
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
        $this->_createClient()->listModels();

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

        // An empty source path is a generation turn, the prompt alone is the input.
        if ($request->sourceImagePath !== '' && !file_exists($request->sourceImagePath)) {
            $result->errorMessage = 'The source image for this edit could not be found.';
            Log::error("Source image not found at \"{$request->sourceImagePath}\".");

            return $result;
        }

        $model = $this->_resolveModel($request->model);
        $tier = $this->clampToSupported($request->resolution, $model);
        $size = $this->_resolveSize($request, $tier);
        $prompt = $this->buildPrompt($request);
        $quality = $this->_resolveQuality($model);
        $complexity = $this->_resolveComplexity($model, $request);
        $format = $this->resolveOutputFormat($request) ?? 'png';

        try {
            if ($request->sourceImagePath === '') {
                $payload = [
                    'model' => $model,
                    'prompt' => $prompt,
                    'size' => $size,
                    'quality' => $quality,
                    'output_format' => $format,
                    'n' => 1,
                ];

                if ($complexity !== null) {
                    $payload['complexity'] = $complexity;
                }

                $response = $client->createImage($payload);
            } else {
                $multipart = [
                    ['name' => 'model', 'contents' => $model],
                    ['name' => 'prompt', 'contents' => $prompt],
                    ['name' => 'size', 'contents' => $size],
                    ['name' => 'quality', 'contents' => $quality],
                    ['name' => 'output_format', 'contents' => $format],
                    ['name' => 'n', 'contents' => '1'],
                    [
                        'name' => 'image',
                        'contents' => fopen($request->sourceImagePath, 'rb'),
                        'filename' => basename($request->sourceImagePath),
                    ],
                ];

                if ($complexity !== null) {
                    $multipart[] = ['name' => 'complexity', 'contents' => $complexity];
                }

                $response = $client->editImage($multipart);
            }
        } catch (OpenAiApiException $openAiApiException) {
            $result->isRefusal = $openAiApiException->isRefusal;
            $result->errorMessage = $openAiApiException->getMessage();
            $result->providerMeta = [
                'statusCode' => $openAiApiException->statusCode,
                'retryAfter' => $openAiApiException->retryAfter,
            ];
            Log::error("OpenAI edit request failed: {$openAiApiException->getMessage()}");

            return $result;
        }

        $imageData = $response['data'][0]['b64_json'] ?? null;
        $decoded = $imageData !== null ? base64_decode($imageData, true) : false;

        if ($decoded === false) {
            $result->errorMessage = 'The model did not return an image for this instruction.';

            return $result;
        }

        $mimeType = 'image/' . $format;

        try {
            $resultImagePath = $this->writeResultImage($request->targetDir, $decoded);
        } catch (EditDriverException|\ErrorException|\RuntimeException $writeException) {
            $result->errorMessage = 'The edited image could not be saved to disk.';
            Log::error("Could not write OpenAI result image: {$writeException->getMessage()}");

            return $result;
        }

        $result->success = true;
        $result->resultImagePath = $resultImagePath;
        $result->mimeType = $mimeType;
        $result->resolutionActual = $tier;
        $result->providerMeta = array_filter([
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
            'complexity' => $complexity,
            'usage' => $response['usage'] ?? null,
        ], static fn($value) => $value !== null);

        return $result;
    }

    // Private Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function analyzeImage(string $imagePath, string $prompt): ?string
    {
        if (!file_exists($imagePath)) {
            return null;
        }

        try {
            $client = $this->_createClient();
        } catch (EditDriverException) {
            return null;
        }

        $mimeType = File::getMimeType($imagePath) ?? 'image/png';
        $dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($imagePath));

        try {
            $response = $client->chatCompletion([
                'model' => $this->getConfig('analysisModel', self::DEFAULT_ANALYSIS_MODEL),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => $dataUrl],
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (OpenAiApiException $openAiApiException) {
            Log::warning("Image analysis failed: {$openAiApiException->getMessage()}");

            return null;
        }

        $text = $response['choices'][0]['message']['content'] ?? null;

        return \is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * Creates an API client with the configured credentials.
     *
     * @throws EditDriverException if no API key is configured
     */
    private function _createClient(): OpenAiApiClient
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === null) {
            throw new EditDriverException('The OpenAI API key is not configured. Set it under `driverConfig.openai.apiKey` in `config/ai-image-editor.php`, typically referencing an `OPENAI_API_KEY` environment variable.');
        }

        return new OpenAiApiClient($apiKey, $this->getRequestTimeout());
    }

    /**
     * Resolves a configured model name to an OpenAI model ID. Retired models
     * are swapped for their replacement, unknown `gpt-image-*` names are
     * passed through, and anything else falls back to the default model.
     */
    private function _resolveModel(?string $model): string
    {
        $model = $this->replaceRetiredModel($model ?? $this->getDefaultModel());

        if (isset(self::MODELS[$model]) || str_starts_with($model, 'gpt-image')) {
            return $model;
        }

        return self::DEFAULT_MODEL;
    }

    /**
     * Resolves the `complexity` level, or null to leave it out of the request.
     * Only the 2.6 models accept it. A configured level wins, otherwise edits
     * with "Precise edits" on use {@see PRECISE_COMPLEXITY}, and everything
     * else gets the API default (`high`).
     */
    private function _resolveComplexity(string $model, EditRequest $request): ?string
    {
        if (!\in_array($model, self::COMPLEXITY_MODELS, true)) {
            return null;
        }

        $complexity = $this->getConfig('complexity');

        if (\is_string($complexity) && $complexity !== '') {
            return $complexity;
        }

        if ($request->sourceImagePath === '' || !$request->preserveContent) {
            return null;
        }

        return self::PRECISE_COMPLEXITY;
    }

    /**
     * Resolves the quality level, clamping the `xhigh` and `max` levels to
     * `high` on models that don't accept them.
     */
    private function _resolveQuality(string $model): string
    {
        $quality = (string)$this->getConfig('quality', 'auto');

        if (\in_array($quality, self::LARGE_OUTPUT_QUALITIES, true) && !\in_array($model, self::LARGE_OUTPUT_MODELS, true)) {
            return 'high';
        }

        return $quality;
    }

    /**
     * Resolves the output size for a resolution tier: an explicitly requested
     * aspect ratio wins, otherwise the source image's ratio is matched to the
     * nearest supported size. Generation turns without a ratio use `auto`.
     */
    private function _resolveSize(EditRequest $request, string $tier): string
    {
        $sizes = self::ASPECT_RATIO_SIZES[$tier] ?? self::ASPECT_RATIO_SIZES['1K'];

        if ($request->aspectRatio !== null && isset($sizes[$request->aspectRatio])) {
            return $sizes[$request->aspectRatio];
        }

        if ($request->sourceImagePath === '') {
            return 'auto';
        }

        $size = @getimagesize($request->sourceImagePath);

        if ($size === false || empty($size[0]) || empty($size[1])) {
            return 'auto';
        }

        $ratio = $size[0] / $size[1];
        $closest = 'auto';
        $smallestDiff = PHP_FLOAT_MAX;

        foreach ($sizes as $sizeValue) {
            [$width, $height] = array_map('intval', explode('x', $sizeValue));
            $diff = abs($ratio - $width / $height);

            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closest = $sizeValue;
            }
        }

        return $closest;
    }
}
