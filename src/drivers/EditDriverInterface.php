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

use spacecatninja\aiimageeditor\exceptions\EditDriverException;
use spacecatninja\aiimageeditor\models\EditRequest;
use spacecatninja\aiimageeditor\models\EditResult;

/**
 * Interface that all edit drivers must implement. The driver is the
 * extensibility seam of the plugin, everything above it (sessions, UI,
 * finalize logic) has no knowledge of the underlying provider.
 *
 * @author André Elvan
 * @since 1.0.0
 */
interface EditDriverInterface
{
    /**
     * Returns the unique driver handle, e.g. `gemini`.
     *
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getHandle(): string;

    /**
     * Returns the display name of the driver, e.g. `Google Gemini`.
     *
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getName(): string;

    /**
     * Returns the models the driver offers for selection, as a map of model
     * value (alias or raw model ID) to display label.
     *
     * @return array<string, string>
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getAvailableModels(): array;

    /**
     * Returns the model a new session should default to: the driver's
     * configured `defaultModel`, or its first available model when unset.
     *
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getDefaultModel(): string;

    /**
     * Returns the resolution tiers the driver can request, e.g. `['1K', '2K', '4K']`.
     *
     * @param string|null $model optional model to scope the answer to, since
     * resolution support can vary between a driver's models
     * @return string[]
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSupportedResolutions(?string $model = null): array;

    /**
     * Returns the highest resolution tier the driver can natively produce.
     *
     * @param string|null $model optional model to scope the answer to
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getMaxResolution(?string $model = null): string;

    /**
     * Returns the resolution tier used for working (draft) edit turns during
     * the chat loop, resolved from per-driver config, the global setting, then
     * the default, and clamped to the driver's supported tiers.
     *
     * @param string|null $model optional model to scope the answer to
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getWorkingResolution(?string $model = null): string;

    /**
     * Returns the default resolution tier for the final result, resolved from
     * per-driver config, the global setting, then the default, and clamped to
     * the driver's supported tiers.
     *
     * @param string|null $model optional model to scope the answer to
     * @return string
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getFinalResolution(?string $model = null): string;

    /**
     * Returns the output aspect ratios the driver can request, e.g.
     * `['1:1', '3:2', '16:9']`. An empty array means the driver offers no
     * explicit ratio control, and output follows the source image.
     *
     * @param string|null $model optional model to scope the answer to
     * @return string[]
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSupportedAspectRatios(?string $model = null): array;

    /**
     * Returns the output image formats the driver can produce, e.g.
     * `['png', 'jpeg', 'webp']`. An empty array means the driver offers no
     * explicit format control, and output is provider-chosen.
     *
     * @param string|null $model optional model to scope the answer to
     * @return string[]
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSupportedOutputFormats(?string $model = null): array;

    /**
     * Describes the main subject of an image in a few words, suitable as the
     * basis for a file name.
     *
     * @param string $imagePath local path to the image
     * @return string|null a short plain-text description, or null when the
     * driver doesn't support analysis or no description could be produced
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function describeImage(string $imagePath): ?string;

    /**
     * Detects the focal point of an image: the spot crops should anchor to.
     *
     * @param string $imagePath local path to the image
     * @return array{x: float, y: float}|null coordinates as fractions of the
     * image dimensions measured from the top left corner, or null when the
     * driver doesn't support detection or no discernible subject was found
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function detectFocalPoint(string $imagePath): ?array;

    /**
     * Returns whether the driver has the configuration it needs to perform
     * edits, e.g. an API key. This must be a cheap, local check with no
     * network traffic, it's called every time the editor is opened.
     *
     * @return bool
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function isConfigured(): bool;

    /**
     * Validates the configured credentials without performing an edit.
     *
     * @return bool
     * @throws EditDriverException if credentials are missing, invalid, or the provider can't be reached
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function testConnection(): bool;

    /**
     * Performs one edit turn.
     *
     * @param EditRequest $request
     * @return EditResult
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function edit(EditRequest $request): EditResult;
}
