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

use spacecatninja\aiimageeditor\clients\GrokApiClient;
use spacecatninja\aiimageeditor\exceptions\EditDriverException;
use spacecatninja\aiimageeditor\exceptions\GrokApiException;
use spacecatninja\aiimageeditor\models\EditRequest;
use spacecatninja\aiimageeditor\models\EditResult;

use yii\base\ErrorException;

/**
 * Edit driver for xAI's Grok Imagine image models.
 *
 * A full-parity driver: xAI has both an instruction-based image edit endpoint
 * and a vision model (`grok-4.5`), so the analysis tasks (focal point
 * detection, descriptive filenames) run in-provider and no `analysisDriver` is
 * needed. The API is OpenAI-compatible and stateless (no multi-turn chaining).
 * Output format is provider-chosen, so no format tier is offered.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class GrokEditDriver extends BaseEditDriver
{
    // Const Properties
    // =========================================================================

    public const HANDLE = 'grok';

    /**
     * @var string The default model used for edits and generation.
     */
    public const DEFAULT_MODEL = 'grok-imagine-image-2.0';

    /**
     * @var string The default vision model used for analysis tasks. Can be
     * overridden with the `analysisModel` driver config.
     */
    public const DEFAULT_ANALYSIS_MODEL = 'grok-4.7';

    /**
     * @var string[] Aspect ratios offered for selection. xAI supports a larger
     * enum, this is a sensible discrete subset for the UI.
     */
    private const ASPECT_RATIOS = ['1:1', '3:2', '2:3', '4:3', '3:4', '16:9', '9:16'];

    /**
     * @var array<string, string> The models offered for selection.
     */
    private const MODELS = [
        'grok-imagine-image-2.0' => 'Grok Imagine 2.0',
        'grok-imagine-image' => 'Grok Imagine',
        'grok-imagine-image-quality' => 'Grok Imagine (Quality)',
    ];

    /**
     * @var string[] Models that accept the `quality` parameter. The older
     * models have no such parameter and the API rejects it.
     */
    private const QUALITY_MODELS = ['grok-imagine-image-2.0'];

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
        return 'xAI Grok';
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

        $isGeneration = $request->sourceImagePath === '';

        if (!$isGeneration && !file_exists($request->sourceImagePath)) {
            $result->errorMessage = 'The source image for this edit could not be found.';
            Craft::error("Source image not found at \"{$request->sourceImagePath}\".", __METHOD__);

            return $result;
        }

        $model = $this->_resolveModel($request->model);
        $tier = $this->_clampResolution($request->resolution);

        $payload = [
            'model' => $model,
            'prompt' => $this->buildPrompt($request),
            'n' => 1,
            'response_format' => 'b64_json',
            'resolution' => strtolower($tier),
        ];

        $quality = $this->getConfig('quality');

        if ($quality !== null && \in_array($model, self::QUALITY_MODELS, true)) {
            $payload['quality'] = (string)$quality;
        }

        try {
            if ($isGeneration) {
                // Text-to-image needs an explicit ratio; default to square.
                $payload['aspect_ratio'] = $request->aspectRatio ?? '1:1';
                $response = $client->createImage($payload);
            } else {
                $mimeType = FileHelper::getMimeType($request->sourceImagePath) ?? 'image/png';
                $payload['image'] = [
                    'type' => 'image_url',
                    'url' => 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($request->sourceImagePath)),
                ];

                // `auto` keeps the source dimensions, i.e. "match original".
                $payload['aspect_ratio'] = $request->aspectRatio ?? 'auto';
                $response = $client->editImage($payload);
            }
        } catch (GrokApiException $grokApiException) {
            $result->isRefusal = $grokApiException->isRefusal;
            $result->errorMessage = $grokApiException->getMessage();
            $result->providerMeta = [
                'statusCode' => $grokApiException->statusCode,
                'retryAfter' => $grokApiException->retryAfter,
            ];
            Craft::error("Grok edit request failed: {$grokApiException->getMessage()}", __METHOD__);

            return $result;
        }

        $data = $response['data'][0] ?? null;
        $imageData = \is_array($data) ? ($data['b64_json'] ?? null) : null;

        if (\is_string($imageData) && str_starts_with($imageData, 'data:')) {
            $comma = strpos($imageData, ',');
            $imageData = $comma !== false ? substr($imageData, $comma + 1) : $imageData;
        }

        $decoded = \is_string($imageData) ? base64_decode($imageData, true) : false;

        if ($decoded === false) {
            $result->errorMessage = 'The model did not return an image for this instruction.';

            return $result;
        }

        $mimeType = (\is_array($data) ? ($data['mime_type'] ?? null) : null) ?: 'image/png';

        try {
            $resultImagePath = $this->writeResultImage($request->targetDir, $decoded);
        } catch (EditDriverException|ErrorException $writeException) {
            $result->errorMessage = 'The edited image could not be saved to disk.';
            Craft::error("Could not write Grok result image: {$writeException->getMessage()}", __METHOD__);

            return $result;
        }

        $result->success = true;
        $result->resultImagePath = $resultImagePath;
        $result->mimeType = $mimeType;
        $result->resolutionActual = $tier;
        $result->providerMeta = array_filter([
            'model' => $model,
            'cost' => $response['usage']['cost_in_usd_ticks'] ?? null,
        ], static fn($value) => $value !== null);

        return $result;
    }

    // Protected Methods
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

        $mimeType = FileHelper::getMimeType($imagePath) ?? 'image/png';
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
        } catch (GrokApiException $grokApiException) {
            Craft::warning("Image analysis failed: {$grokApiException->getMessage()}", __METHOD__);

            return null;
        }

        $text = $response['choices'][0]['message']['content'] ?? null;

        return \is_string($text) && $text !== '' ? $text : null;
    }

    // Private Methods
    // =========================================================================

    /**
     * Creates an API client with the configured credentials.
     *
     * @throws EditDriverException if no API key is configured
     */
    private function _createClient(): GrokApiClient
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === null) {
            throw new EditDriverException('The xAI API key is not configured. Set it under `driverConfig.grok.apiKey` in `config/ai-image-editor.php`, typically referencing an `XAI_API_KEY` environment variable.');
        }

        return new GrokApiClient($apiKey, $this->getRequestTimeout());
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
     * back to the highest supported tier.
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
}
