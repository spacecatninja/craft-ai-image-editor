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

use craft\base\Component;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;

use spacecatninja\aiimageeditor\AiImageEditor;
use spacecatninja\aiimageeditor\exceptions\EditDriverException;
use spacecatninja\aiimageeditor\helpers\AnalysisHelper;
use spacecatninja\aiimageeditor\models\EditRequest;

use yii\base\ErrorException;

/**
 * Base class for edit drivers, holding the behavior shared across providers:
 * per-driver config reading, credentials, prompt building, and the analysis
 * task wrappers. Drivers read their own config from
 * `$settings->driverConfig[$handle]`, so bundled and third-party drivers alike
 * get typed-free configuration without touching the core settings model.
 *
 * @author André Elvan
 * @since 1.0.0
 */
abstract class BaseEditDriver extends Component implements EditDriverInterface
{
    // Const Properties
    // =========================================================================

    /**
     * @var string The default instructions appended to edit prompts when
     * content preservation is enabled, biasing the model toward minimal,
     * faithful edits. Overridable per-driver, or globally with the
     * `preserveInstructions` setting.
     */
    public const DEFAULT_PRESERVE_INSTRUCTIONS = 'Keep everything else in the image exactly the same, preserving the original style, lighting, and composition. Do not add, remove, or alter anything that was not explicitly requested.';

    /**
     * @var string[] The known resolution tiers, lowest to highest, used to
     * order and cap the tiers a driver exposes.
     */
    private const RESOLUTION_ORDER = ['512', '1K', '2K', '4K'];

    /**
     * @var array<int, string> Supported result image types (`IMAGETYPE_*`)
     * mapped to the file extension they're written with.
     */
    private const IMAGE_TYPE_EXTENSIONS = [
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getSupportedResolutions(?string $model = null): array
    {
        return $this->capResolutions($this->nativeResolutions($model));
    }

    /**
     * @inheritdoc
     */
    public function getWorkingResolution(?string $model = null): string
    {
        $settings = AiImageEditor::$plugin?->getSettings();
        $value = $this->getConfig('workingResolution') ?? $settings?->workingResolution ?? '1K';

        return $this->clampToSupported((string)$value, $model);
    }

    /**
     * @inheritdoc
     */
    public function getFinalResolution(?string $model = null): string
    {
        $settings = AiImageEditor::$plugin?->getSettings();
        $value = $this->getConfig('finalResolution') ?? $settings?->finalResolution ?? '2K';

        return $this->clampToSupported((string)$value, $model);
    }

    /**
     * @inheritdoc
     */
    public function getMaxResolution(?string $model = null): string
    {
        $resolutions = $this->getSupportedResolutions($model);
        $last = end($resolutions);

        return $last !== false ? $last : '';
    }

    /**
     * @inheritdoc
     */
    public function getSupportedOutputFormats(?string $model = null): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getDefaultModel(): string
    {
        $configured = $this->getConfig('defaultModel');

        if (\is_string($configured) && $configured !== '') {
            return $configured;
        }

        $models = $this->getAvailableModels();

        return $models !== [] ? (string)array_key_first($models) : '';
    }

    /**
     * @inheritdoc
     */
    public function describeImage(string $imagePath): ?string
    {
        return AnalysisHelper::parseDescription(
            $this->analyzeImage($imagePath, AnalysisHelper::DESCRIBE_IMAGE_PROMPT)
        );
    }

    /**
     * @inheritdoc
     */
    public function detectFocalPoint(string $imagePath): ?array
    {
        return AnalysisHelper::parseFocalPoint(
            $this->analyzeImage($imagePath, AnalysisHelper::FOCAL_POINT_PROMPT)
        );
    }

    /**
     * @inheritdoc
     */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== null;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Runs an image analysis prompt against the driver's analysis model and
     * returns the text output. Must return null on any failure, analysis is
     * always best-effort.
     *
     * @param string $imagePath
     * @param string $prompt
     * @return string|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    abstract protected function analyzeImage(string $imagePath, string $prompt): ?string;

    /**
     * Writes result image bytes to a uniquely named file in the target
     * directory, after verifying the bytes are a real raster image. The
     * extension is derived from the detected image type (an allowlist of
     * png/jpeg/webp/gif), never from the provider's declared mime type, so a
     * mislabeled or non-image payload can't be written, or later served inline
     * as active content.
     *
     * @param string $targetDir the directory to write into
     * @param string $data the raw (already decoded) image bytes
     * @return string the absolute path to the written file
     * @throws EditDriverException if the target directory is unset, or the bytes aren't a supported image
     * @throws ErrorException if the file can't be written
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function writeResultImage(string $targetDir, string $data): string
    {
        if ($targetDir === '') {
            throw new EditDriverException('No target directory was set on the edit request.');
        }

        $info = @getimagesizefromstring($data);
        $extension = $info !== false ? (self::IMAGE_TYPE_EXTENSIONS[$info[2]] ?? null) : null;

        if ($extension === null) {
            throw new EditDriverException('The provider returned data that is not a supported image.');
        }

        $filename = sprintf('result-%s.%s', strtolower(StringHelper::randomString(16)), $extension);
        $path = rtrim($targetDir, '/') . '/' . $filename;

        FileHelper::writeToFile($path, $data);

        return $path;
    }

    /**
     * Returns a value from this driver's config block
     * (`driverConfig[$handle]`), or the given default when it isn't set.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        $driverConfig = AiImageEditor::$plugin?->getSettings()?->driverConfig ?? [];
        $config = $driverConfig[$this->getHandle()] ?? [];

        return $config[$key] ?? $default;
    }

    /**
     * Returns the driver's configured API key with any environment variable
     * reference resolved, or null when it isn't configured.
     *
     * @return string|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function getApiKey(): ?string
    {
        $apiKey = App::parseEnv($this->getConfig('apiKey'));

        return \is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
    }

    /**
     * Returns the request timeout in seconds.
     *
     * @return int
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function getRequestTimeout(): int
    {
        return AiImageEditor::$plugin?->getSettings()?->requestTimeout ?? 120;
    }

    /**
     * Builds the effective prompt for an edit turn, appending the content
     * preservation instructions when the request asks for them.
     *
     * @param EditRequest $request
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function buildPrompt(EditRequest $request): string
    {
        if (!$request->preserveContent) {
            return $request->prompt;
        }

        $instructions = AiImageEditor::$plugin?->getSettings()?->preserveInstructions
            ?? static::DEFAULT_PRESERVE_INSTRUCTIONS;

        if (trim($instructions) === '') {
            return $request->prompt;
        }

        return $request->prompt . "\n\n" . $instructions;
    }

    /**
     * Resolves the output format for a request: the requested format wins,
     * then the driver's configured `outputFormat`, falling back to the
     * driver's first supported format. Returns null when the driver offers no
     * format control.
     *
     * @param EditRequest $request
     * @param string|null $model optional model to scope supported formats to
     * @return string|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function resolveOutputFormat(EditRequest $request, ?string $model = null): ?string
    {
        $supported = $this->getSupportedOutputFormats($model);

        if ($supported === []) {
            return null;
        }

        $format = $request->outputFormat ?? $this->getConfig('outputFormat');

        if (\is_string($format) && \in_array($format, $supported, true)) {
            return $format;
        }

        return $supported[0];
    }

    /**
     * Returns the resolution tiers the driver can natively produce, lowest to
     * highest. Drivers override this; the public `getSupportedResolutions()`
     * applies the `maxResolution` cap on top of it.
     *
     * @param string|null $model optional model to scope the answer to
     * @return string[]
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function nativeResolutions(?string $model = null): array
    {
        return ['1K'];
    }

    /**
     * Applies the configured `maxResolution` cap (per-driver, then global) to a
     * list of tiers, dropping any above the cap. Unknown tiers are kept, and
     * the result is never empty (the lowest native tier survives a too-low cap).
     *
     * @param string[] $tiers
     * @return string[]
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function capResolutions(array $tiers): array
    {
        $max = $this->getConfig('maxResolution') ?? AiImageEditor::$plugin?->getSettings()?->maxResolution;

        if (!\is_string($max) || $max === '') {
            return $tiers;
        }

        $maxOrder = array_search($max, self::RESOLUTION_ORDER, true);

        if ($maxOrder === false) {
            return $tiers;
        }

        $capped = array_values(array_filter($tiers, static function(string $tier) use ($maxOrder): bool {
            $order = array_search($tier, self::RESOLUTION_ORDER, true);

            return $order === false || $order <= $maxOrder;
        }));

        return $capped !== [] ? $capped : \array_slice($tiers, 0, 1);
    }

    /**
     * Clamps a resolution tier to the driver's supported (capped) tiers:
     * returns it as-is if supported, otherwise the highest supported tier at or
     * below it, otherwise the lowest supported tier.
     *
     * @param string $tier
     * @param string|null $model optional model to scope supported tiers to
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    protected function clampToSupported(string $tier, ?string $model = null): string
    {
        $supported = $this->getSupportedResolutions($model);

        if ($supported === [] || \in_array($tier, $supported, true)) {
            return $tier;
        }

        $tierOrder = array_search($tier, self::RESOLUTION_ORDER, true);
        $best = null;
        $bestOrder = -1;

        foreach ($supported as $candidate) {
            $order = array_search($candidate, self::RESOLUTION_ORDER, true);

            if ($order === false) {
                continue;
            }

            if (($tierOrder === false || $order <= $tierOrder) && $order > $bestOrder) {
                $best = $candidate;
                $bestOrder = $order;
            }
        }

        return $best ?? $supported[0];
    }
}
