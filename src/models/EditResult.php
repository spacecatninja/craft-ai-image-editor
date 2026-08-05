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
 * The result of one edit turn performed by an edit driver.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditResult extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string|null Error message suitable for surfacing in the editor UI
     * when the turn failed.
     */
    public ?string $errorMessage = null;

    /**
     * @var bool Whether the failure was a provider content-policy refusal, as
     * opposed to a technical error. Refusals are surfaced as a turn in the
     * chat so the user understands why the edit was rejected.
     */
    public bool $isRefusal = false;

    /**
     * @var string|null The mime type of the result image.
     */
    public ?string $mimeType = null;

    /**
     * @var array Raw response bits worth keeping, used for debugging, cost
     * logs, and as multi-turn provider state for the next request.
     */
    public array $providerMeta = [];

    /**
     * @var string|null The resolution tier that was actually produced, which
     * may differ from what was requested.
     */
    public ?string $resolutionActual = null;

    /**
     * @var string|null Local path to the returned image.
     */
    public ?string $resultImagePath = null;

    /**
     * @var bool Whether the edit succeeded.
     */
    public bool $success = false;
}
