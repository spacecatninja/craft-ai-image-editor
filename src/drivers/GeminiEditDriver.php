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
use craft\helpers\FileHelper;

use spacecatninja\aiimageeditor\clients\GeminiApiClient;
use spacecatninja\aiimageeditor\exceptions\EditDriverException;
use spacecatninja\aiimageeditor\exceptions\GeminiApiException;
use spacecatninja\aiimageeditor\models\EditRequest;
use spacecatninja\aiimageeditor\models\EditResult;

use yii\base\ErrorException;

/**
 * Edit driver for the Google Gemini image models (Nano Banana).
 *
 * @author André Elvan
 * @since 1.0.0
 */
class GeminiEditDriver extends BaseEditDriver
{
    // Const Properties
    // =========================================================================

    public const HANDLE = 'gemini';

    /**
     * @var string The default model used for analysis tasks like focal point
     * detection. Can be overridden with the `analysisModel` driver config.
     */
    public const DEFAULT_ANALYSIS_MODEL = 'gemini-3.5-flash';

    /**
     * @var array<string, float> Aspect ratios supported by the API, mapped to their numeric values.
     */
    private const ASPECT_RATIOS = [
        '1:1' => 1.0,
        '3:2' => 1.5,
        '2:3' => 0.6667,
        '3:4' => 0.75,
        '4:3' => 1.3333,
        '4:5' => 0.8,
        '5:4' => 1.25,
        '9:16' => 0.5625,
        '16:9' => 1.7778,
        '21:9' => 2.3333,
    ];

    /**
     * @var array<string, string> Friendly model aliases mapped to Gemini model IDs.
     */
    private const MODEL_ALIASES = [
        'nano-banana-2' => 'gemini-3.1-flash-image',
        'nano-banana-pro' => 'gemini-3-pro-image',
    ];

    /**
     * @var array<string, string[]> Resolution tiers supported per model, lowest to highest.
     */
    private const MODEL_RESOLUTIONS = [
        'gemini-3.1-flash-lite-image' => ['1K'],
        'gemini-3.1-flash-image' => ['512', '1K', '2K', '4K'],
        'gemini-3-pro-image' => ['1K', '2K', '4K'],
    ];

    /**
     * @var string[] Resolutions assumed for unknown models.
     */
    private const FALLBACK_RESOLUTIONS = ['1K'];

    /**
     * @var string[] Models that accept the `thinking_level` generation config option.
     */
    private const THINKING_LEVEL_MODELS = ['gemini-3.1-flash-image', 'gemini-3.1-flash-lite-image'];

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
        return 'Google Gemini';
    }

    /**
     * @inheritdoc
     */
    public function getAvailableModels(): array
    {
        return [
            'nano-banana-pro' => 'Nano Banana Pro',
            'nano-banana-2' => 'Nano Banana 2',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function nativeResolutions(?string $model = null): array
    {
        $modelId = $this->_resolveModel($model);

        return self::MODEL_RESOLUTIONS[$modelId] ?? self::FALLBACK_RESOLUTIONS;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedAspectRatios(?string $model = null): array
    {
        return array_keys(self::ASPECT_RATIOS);
    }

    /**
     * @inheritdoc
     *
     * The interactions API only accepts `image/jpeg` for
     * `response_format.mime_type` (both `image/png` and `image/webp` are
     * rejected, and omitting it also yields JPEG), so JPEG is the only format
     * Gemini can produce.
     */
    public function getSupportedOutputFormats(?string $model = null): array
    {
        return ['jpeg'];
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
            Craft::error("Source image not found at \"{$request->sourceImagePath}\".", __METHOD__);

            return $result;
        }

        $model = $this->_resolveModel($request->model);
        $resolution = $this->_clampResolution($request->resolution, $model);
        $prompt = $this->buildPrompt($request);
        $previousInteractionId = $request->providerState['interactionId'] ?? null;

        if (!\is_string($previousInteractionId) || $previousInteractionId === '') {
            $previousInteractionId = null;
        }

        // Interaction chaining only holds within one model. After a model
        // switch the turn runs statelessly with the working image attached.
        $previousModel = $request->providerState['model'] ?? null;

        if (
            $previousInteractionId !== null
            && \is_string($previousModel)
            && $previousModel !== ''
            && !str_contains($previousModel, $model)
            && !str_contains($model, $previousModel)
        ) {
            $previousInteractionId = null;
        }

        $payload = $this->_buildPayload($request, $model, $prompt, $resolution, $previousInteractionId);

        try {
            $response = $client->createImageInteraction($payload);
        } catch (GeminiApiException $geminiApiException) {
            $response = null;

            // If the provider no longer recognizes the previous interaction,
            // fall back to a stateless request with the working image attached.
            if ($previousInteractionId !== null && \in_array($geminiApiException->statusCode, [400, 404, 422], true)) {
                Craft::warning("Gemini rejected previous interaction \"{$previousInteractionId}\", retrying statelessly: {$geminiApiException->getMessage()}", __METHOD__);

                try {
                    $response = $client->createImageInteraction($this->_buildPayload($request, $model, $prompt, $resolution, null));
                } catch (GeminiApiException $retryException) {
                    $geminiApiException = $retryException;
                }
            }

            if ($response === null) {
                $result->errorMessage = $geminiApiException->getMessage();
                $result->providerMeta = [
                    'statusCode' => $geminiApiException->statusCode,
                    'retryAfter' => $geminiApiException->retryAfter,
                ];
                Craft::error("Gemini edit request failed: {$geminiApiException->getMessage()}", __METHOD__);

                return $result;
            }
        }

        $image = $this->_extractImage($response);
        $outputText = $this->_extractOutputText($response);

        if ($image === null) {
            // No image in the response usually means the model declined the edit,
            // surface its own explanation in the chat when there is one.
            $result->isRefusal = true;
            $result->errorMessage = $outputText !== null && $outputText !== ''
                ? $outputText
                : 'The model did not return an image for this instruction.';
            $result->providerMeta = $this->_buildProviderMeta($response, $outputText);

            return $result;
        }

        try {
            $resultImagePath = $this->writeResultImage($request->targetDir, $image['data']);
        } catch (EditDriverException|ErrorException $writeException) {
            $result->errorMessage = 'The edited image could not be saved to disk.';
            Craft::error("Could not write Gemini result image: {$writeException->getMessage()}", __METHOD__);

            return $result;
        }

        $result->success = true;
        $result->resultImagePath = $resultImagePath;
        $result->mimeType = $image['mimeType'];
        $result->resolutionActual = $resolution;
        $result->providerMeta = $this->_buildProviderMeta($response, $outputText);

        return $result;
    }

    // Private Methods
    // =========================================================================

    /**
     * Creates an API client with the configured credentials.
     *
     * @throws EditDriverException if no API key is configured
     */
    private function _createClient(): GeminiApiClient
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === null) {
            throw new EditDriverException('The Gemini API key is not configured. Set it under `driverConfig.gemini.apiKey` in `config/ai-image-editor.php`, typically referencing a `GEMINI_API_KEY` environment variable.');
        }

        return new GeminiApiClient($apiKey, $this->getRequestTimeout());
    }

    /**
     * Builds the interaction payload for one edit turn. When a previous
     * interaction ID is given the working image is not re-uploaded, the model
     * continues from its own context instead, which is what Google recommends
     * for iterating on images and reduces compounding drift.
     */
    private function _buildPayload(EditRequest $request, string $model, string $prompt, string $resolution, ?string $previousInteractionId): array
    {
        $input = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];

        if ($previousInteractionId === null && $request->sourceImagePath !== '') {
            $input[] = [
                'type' => 'image',
                'mime_type' => FileHelper::getMimeType($request->sourceImagePath) ?? 'image/png',
                'data' => base64_encode(file_get_contents($request->sourceImagePath)),
            ];
        }

        $responseFormat = [
            'type' => 'image',
            'image_size' => $resolution,
        ];

        // An explicitly requested ratio wins, otherwise match the source image.
        $aspectRatio = isset(self::ASPECT_RATIOS[$request->aspectRatio])
            ? $request->aspectRatio
            : $this->_resolveAspectRatio($request->sourceImagePath);

        if ($aspectRatio !== null) {
            $responseFormat['aspect_ratio'] = $aspectRatio;
        }

        $outputFormat = $this->resolveOutputFormat($request, $model);

        if ($outputFormat !== null) {
            $responseFormat['mime_type'] = 'image/' . $outputFormat;
        }

        $payload = [
            'model' => $model,
            'input' => $input,
            'response_format' => $responseFormat,
        ];

        if ($previousInteractionId !== null) {
            $payload['previous_interaction_id'] = $previousInteractionId;
        }

        $thinkingLevel = $this->getConfig('thinkingLevel');

        if (\is_string($thinkingLevel) && $thinkingLevel !== '' && \in_array($model, self::THINKING_LEVEL_MODELS, true)) {
            $payload['generation_config'] = [
                'thinking_level' => $thinkingLevel,
            ];
        }

        return $payload;
    }

    /**
     * Returns the supported aspect ratio closest to the source image's, so
     * the output keeps the source composition instead of being recomposed to
     * a default ratio. Returns null if the image dimensions can't be read.
     */
    private function _resolveAspectRatio(string $sourceImagePath): ?string
    {
        if ($sourceImagePath === '') {
            return null;
        }

        $size = @getimagesize($sourceImagePath);

        if ($size === false || empty($size[0]) || empty($size[1])) {
            return null;
        }

        $ratio = $size[0] / $size[1];
        $closest = null;
        $smallestDiff = PHP_FLOAT_MAX;

        foreach (self::ASPECT_RATIOS as $label => $value) {
            $diff = abs($ratio - $value);

            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closest = $label;
            }
        }

        return $closest;
    }

    /**
     * Resolves a configured model name to a Gemini model ID. Unknown names are
     * passed through untouched, so raw model IDs keep working.
     */
    private function _resolveModel(?string $model): string
    {
        $model = $model ?? $this->getDefaultModel();

        return self::MODEL_ALIASES[$model] ?? $model;
    }

    /**
     * Clamps a requested resolution tier to what the model supports, falling
     * back to the model's highest tier when the requested one is unavailable.
     */
    private function _clampResolution(string $resolution, string $modelId): string
    {
        $supported = $this->getSupportedResolutions($modelId);

        if (\in_array($resolution, $supported, true)) {
            return $resolution;
        }

        $last = end($supported);

        return $last !== false ? $last : $resolution;
    }

    /**
     * Extracts the first image from an interaction response.
     *
     * @param array $response
     * @return array{data: string, mimeType: string}|null decoded image bytes and mime type
     */
    private function _extractImage(array $response): ?array
    {
        // Prefer the convenience property when present.
        $imageData = $response['output_image']['data'] ?? null;
        $mimeType = $response['output_image']['mime_type'] ?? null;

        if ($imageData === null) {
            foreach ($response['steps'] ?? [] as $step) {
                foreach ($step['content'] ?? [] as $part) {
                    if (($part['type'] ?? null) === 'image' && isset($part['data'])) {
                        $imageData = $part['data'];
                        $mimeType = $part['mime_type'] ?? null;
                        break 2;
                    }
                }
            }
        }

        if ($imageData === null) {
            return null;
        }

        $decoded = base64_decode($imageData, true);

        if ($decoded === false) {
            return null;
        }

        return [
            'data' => $decoded,
            'mimeType' => $mimeType ?? 'image/png',
        ];
    }

    /**
     * Extracts any text output from an interaction response.
     */
    private function _extractOutputText(array $response): ?string
    {
        $text = $response['output_text'] ?? null;

        if (\is_string($text) && $text !== '') {
            return $text;
        }

        $textParts = [];

        foreach ($response['steps'] ?? [] as $step) {
            foreach ($step['content'] ?? [] as $part) {
                if (($part['type'] ?? null) === 'text' && isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }
        }

        return $textParts !== [] ? implode("\n", $textParts) : null;
    }

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

        $payload = [
            'model' => $this->getConfig('analysisModel', self::DEFAULT_ANALYSIS_MODEL),
            'input' => [
                [
                    'type' => 'text',
                    'text' => $prompt,
                ],
                [
                    'type' => 'image',
                    'mime_type' => FileHelper::getMimeType($imagePath) ?? 'image/png',
                    'data' => base64_encode(file_get_contents($imagePath)),
                ],
            ],
        ];

        try {
            $response = $client->createTextInteraction($payload);
        } catch (GeminiApiException $geminiApiException) {
            Craft::warning("Image analysis failed: {$geminiApiException->getMessage()}", __METHOD__);

            return null;
        }

        return $this->_extractOutputText($response);
    }

    /**
     * Builds the provider metadata stored with a turn, for debugging and cost logs.
     */
    private function _buildProviderMeta(array $response, ?string $outputText): array
    {
        return array_filter([
            'interactionId' => $response['id'] ?? null,
            'model' => $response['model'] ?? null,
            'usage' => $response['usage'] ?? null,
            'outputText' => $outputText,
        ], static fn($value) => $value !== null);
    }
}
