<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Support;

use CraftCms\Cms\Support\Str;

/**
 * String helpers that Craft 5 had, and Craft 6 doesn't.
 */
class Strings
{
    /**
     * Converts a string to lowercase words joined by hyphens, without punctuation.
     */
    public static function toKebabCase(string $str): string
    {
        $words = array_map(static fn(string $word): string => trim($word, '-'), Str::toWords($str, true, true));

        return implode('-', array_filter($words, static fn(string $word): bool => $word !== ''));
    }

    /**
     * Truncates a string to a length, without splitting words.
     */
    public static function safeTruncate(string $str, int $length): string
    {
        return rtrim(Str::limit($str, $length, '', true));
    }
}
