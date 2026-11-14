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

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use SpaceCatNinja\AiImageEditor\Data\EditRequest;
use SpaceCatNinja\AiImageEditor\Data\EditSession;

/**
 * Dispatched before each turn is sent to the driver.
 *
 * Set `$isValid` to `false` to cancel the edit, or replace `$request` to send a different request.
 */
class EditingImage
{
    use ValidatableEvent;

    public function __construct(
        public readonly EditSession $session,
        public EditRequest $request,
    ) {
    }
}
