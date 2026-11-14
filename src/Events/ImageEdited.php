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

use SpaceCatNinja\AiImageEditor\Data\EditRequest;
use SpaceCatNinja\AiImageEditor\Data\EditResult;
use SpaceCatNinja\AiImageEditor\Data\EditSession;

/**
 * Dispatched after a turn succeeded.
 */
class ImageEdited
{
    public function __construct(
        public readonly EditSession $session,
        public readonly EditRequest $request,
        public readonly EditResult $result,
    ) {
    }
}
