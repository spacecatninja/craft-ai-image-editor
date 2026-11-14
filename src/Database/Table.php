<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Database;

/**
 * The plugin's database tables, without the table prefix. The database connection adds it.
 */
abstract class Table
{
    public const string SESSIONS = 'aiimageeditor_sessions';
    public const string TURNS = 'aiimageeditor_turns';
}
