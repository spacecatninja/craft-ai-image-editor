<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\db;

/**
 * Database table names used by the plugin.
 *
 * @author André Elvan
 * @since 1.0.0
 */
abstract class Table
{
    // Const Properties
    // =========================================================================

    public const SESSIONS = '{{%aiimageeditor_sessions}}';
    public const TURNS = '{{%aiimageeditor_turns}}';
}
