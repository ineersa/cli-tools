<?php

namespace App\Render;

/**
 * Utility helpers for working with "visible" text by removing formatting such as
 * HTML tags and ANSI escape codes before performing operations like length and substring.
 */
class Utils
{
    /**
     * Get the visible length of a string after stripping HTML tags and ANSI escape codes.
     *
     * Note: Uses strlen; the result is measured in bytes for multibyte encodings.
     *
     * @param string $s Input string.
     * @return int Visible length in bytes.
     */
    public static function vlen(string $s): int
    {
        return strlen(self::vstrip($s));
    }

    /**
     * Strip HTML tags and ANSI color/control escape sequences from the given string.
     *
     * Removes:
     * - HTML/XML tags via strip_tags
     * - ANSI escape sequences like ESC[...m (e.g., color codes)
     *
     * @param string $s Input string.
     * @return string Cleaned string containing only visible characters.
     */
    public static function vstrip(string $s): string
    {
        $noTags = strip_tags($s);
        return preg_replace('/\e\[[\d;]*m/', '', $noTags) ?? $noTags;
    }

    /**
     * Get a visible substring after stripping HTML tags and ANSI escape codes.
     *
     * Note: Uses substr on the cleaned string; offsets and lengths are in bytes for multibyte encodings.
     *
     * @param string $s Input string.
     * @param int $start Starting position in the cleaned string (0-based, bytes).
     * @param int $len Maximum length of the substring (bytes).
     * @return string Visible substring.
     */
    public static function vsubstr(string $s, int $start, int $len): string
    {
        return substr(self::vstrip($s), $start, $len);
    }
}
