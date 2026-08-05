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

use craft\elements\Asset;

use spacecatninja\aiimageeditor\models\EditSession;

use yii\base\Event;

/**
 * Event triggered before and after a session's result is saved as an asset.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class SaveEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var EditSession The session being saved.
     */
    public EditSession $session;

    /**
     * @var Asset The asset being saved: the source asset when replacing, or the
     * new asset otherwise. Handlers of the "before" event may set fields on it.
     */
    public Asset $asset;

    /**
     * @var bool Whether the save replaces the original asset's file, rather
     * than creating a new asset.
     */
    public bool $replace = false;

    /**
     * @var bool Whether the save should proceed. Set to false in a "before"
     * handler to cancel it.
     */
    public bool $isValid = true;
}
