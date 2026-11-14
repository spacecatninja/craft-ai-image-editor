<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Events;

use CraftCms\Cms\Asset\Elements\Asset;
use SpaceCatNinja\AiImageEditor\Data\EditSession;

/**
 * Dispatched after the result of a session was saved as an asset.
 */
class ImageSaved
{
    public function __construct(
        public readonly EditSession $session,
        public readonly Asset $asset,
        public readonly bool $replace,
    ) {
    }
}
