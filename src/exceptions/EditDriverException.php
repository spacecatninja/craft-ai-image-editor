<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\exceptions;

use Exception;

/**
 * Exception thrown by edit drivers when configuration is missing or the
 * provider can't be used.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditDriverException extends Exception
{
}
