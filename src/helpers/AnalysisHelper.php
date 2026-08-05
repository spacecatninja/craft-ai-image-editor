<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\helpers;

use craft\helpers\Json;
use craft\helpers\StringHelper;

/**
 * Shared prompts and response parsing for the image analysis tasks that
 * drivers implement (focal point detection, image description).
 *
 * @author André Elvan
 * @since 1.0.0
 */
class AnalysisHelper
{
    // Const Properties
    // =========================================================================

    /**
     * @var string Prompt asking for a short subject description usable as a file name.
     */
    public const DESCRIBE_IMAGE_PROMPT = 'Describe the main subject of this image in 3 to 6 words, suitable for use as a file name. Respond with only those words in plain lowercase text, no punctuation, no quotes, no other text.';

    /**
     * @var string Prompt asking for focal point coordinates as JSON.
     */
    public const FOCAL_POINT_PROMPT = <<<'PROMPT'
Identify the single most important focal point of this image, used to anchor crops when the image is displayed at different aspect ratios.
- The focal point is the spot a viewer's eye should be drawn to. Priority order: human faces, then people or animals, then the main subject, then the area of sharpest focus or highest contrast.
- Coordinates are relative to the image dimensions: x is the horizontal position measured from the left edge (0.0 = left, 1.0 = right); y is the vertical position measured from the top edge (0.0 = top, 1.0 = bottom).
- Aim for the center of the subject (for faces, the point between the eyes).
- Be precise; do not default to x 0.5 and y 0.5 unless the subject is genuinely centered.
Respond with only a JSON object, no other text: {"x": 0.62, "y": 0.38}
If there is no discernible subject (flat textures, abstract gradients, uniform patterns), respond with: {"x": null, "y": null}
PROMPT;

    // Public Methods
    // =========================================================================

    /**
     * Parses focal point coordinates from a model response, tolerating
     * markdown fences and surrounding text. Returns null when no valid,
     * non-null coordinate pair is found.
     *
     * @param string|null $text
     * @return array{x: float, y: float}|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public static function parseFocalPoint(?string $text): ?array
    {
        if ($text === null || $text === '') {
            return null;
        }

        if (!preg_match('/\{[^{}]*\}/', $text, $matches)) {
            return null;
        }

        $data = Json::decodeIfJson($matches[0]);

        if (!\is_array($data) || !isset($data['x'], $data['y']) || !is_numeric($data['x']) || !is_numeric($data['y'])) {
            return null;
        }

        // Clamp to the 0-1 range, Asset::setFocalPoint() silently discards
        // out-of-range values.
        return [
            'x' => min(1.0, max(0.0, round((float)$data['x'], 4))),
            'y' => min(1.0, max(0.0, round((float)$data['y'], 4))),
        ];
    }

    /**
     * Extracts a short single-line description from a model response, capped
     * at a filename-friendly length.
     *
     * @param string|null $text
     * @return string|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public static function parseDescription(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $firstLine = strtok(trim($text), "\n");

        if (!\is_string($firstLine) || trim($firstLine) === '') {
            return null;
        }

        return StringHelper::safeTruncate(trim($firstLine), 60);
    }
}
