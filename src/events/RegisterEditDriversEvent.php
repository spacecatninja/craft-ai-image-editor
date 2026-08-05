<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\events;

use yii\base\Event;

/**
 * Event for registering edit drivers, keyed by driver handle.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class RegisterEditDriversEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var array<string, class-string> List of edit driver classes, indexed by driver handle.
     */
    public array $drivers = [];
}
