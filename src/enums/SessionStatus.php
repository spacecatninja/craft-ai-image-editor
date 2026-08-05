<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\enums;

/**
 * The lifecycle states of an edit session.
 *
 * @author André Elvan
 * @since 1.0.0
 */
enum SessionStatus: string
{
    case Active = 'active';
    case Finalized = 'finalized';
    case Discarded = 'discarded';
}
