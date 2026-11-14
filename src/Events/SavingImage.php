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
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use SpaceCatNinja\AiImageEditor\Data\EditSession;

/**
 * Dispatched before the result of a session is saved as an asset.
 *
 * Set `$isValid` to `false` to cancel the save. `$replace` is `true` when the original asset's file is replaced.
 */
class SavingImage
{
    use ValidatableEvent;

    public function __construct(
        public readonly EditSession $session,
        public readonly Asset $asset,
        public readonly bool $replace,
    ) {
    }
}
