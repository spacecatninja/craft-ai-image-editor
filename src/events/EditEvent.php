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

use spacecatninja\aiimageeditor\models\EditRequest;
use spacecatninja\aiimageeditor\models\EditResult;
use spacecatninja\aiimageeditor\models\EditSession;

use yii\base\Event;

/**
 * Event triggered before and after an edit turn runs against a driver.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var EditSession The session the edit belongs to.
     */
    public EditSession $session;

    /**
     * @var EditRequest The request handed to the driver. Handlers of the
     * "before" event may mutate or replace it to influence the edit.
     */
    public EditRequest $request;

    /**
     * @var EditResult|null The driver result. Null on the "before" event, set
     * on the "after" event.
     */
    public ?EditResult $result = null;

    /**
     * @var bool Whether the edit should proceed. Set to false in a "before"
     * handler to cancel the turn before the driver is called.
     */
    public bool $isValid = true;
}
