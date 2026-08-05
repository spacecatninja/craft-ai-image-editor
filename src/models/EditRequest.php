<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\models;

use craft\base\Model;

/**
 * Describes one edit turn to be performed by an edit driver.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditRequest extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string|null The requested output aspect ratio, e.g. `1:1`. If
     * null, the output follows the source image's aspect ratio.
     */
    public ?string $aspectRatio = null;

    /**
     * @var string|null Driver-specific model variant, e.g. `nano-banana-pro`.
     * If null, the driver's default is used.
     */
    public ?string $model = null;

    /**
     * @var string|null The requested output image format, e.g. `png`, `jpeg`
     * or `webp`. If null, the driver falls back to its configured or default
     * format.
     */
    public ?string $outputFormat = null;

    /**
     * @var bool Whether the driver should append its content-preservation
     * instructions to the prompt, biasing the model toward minimal,
     * faithful edits.
     */
    public bool $preserveContent = true;

    /**
     * @var string The user's natural-language instruction.
     */
    public string $prompt = '';

    /**
     * @var array|null Opaque provider state from the previous turn (the
     * previous turn's `providerMeta`), for drivers that support multi-turn
     * state. Drivers must not require it, a null value means a fresh turn.
     */
    public ?array $providerState = null;

    /**
     * @var string The requested resolution tier, e.g. `1K` or `4K`. Drivers
     * clamp this to the closest supported tier, and report what was actually
     * used through `EditResult::$resolutionActual`.
     */
    public string $resolution = '1K';

    /**
     * @var string Local path to the current working image that the edit is
     * applied to.
     */
    public string $sourceImagePath = '';

    /**
     * @var string Local directory the driver must write its result image to.
     * The caller owns the directory and its lifecycle.
     */
    public string $targetDir = '';
}
