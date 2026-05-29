<?php

declare(strict_types=1);

namespace Arc\Config;

/**
 * Simple .env file parser. Loads key=value pairs from a .env file into
 * $_ENV and $_SERVER, making them available via getenv().
 *
 * Lines starting with # are comments. Empty lines are skipped.
 * Values can be quoted (single or double) and trailing comments are supported.
 */
class EnvLoader
{
    /**
     * Load environment variables from a .env file.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            // Skip lines without =
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // Don't overwrite existing environment variables
            if (getenv($key) !== false) {
                continue;
            }

            // Remove surrounding quotes
            $value = self::stripQuotes($value);

            // Strip inline comments after unquoted values
            if (!str_starts_with(trim($value), '"') && !str_starts_with(trim($value), "'")) {
                $value = self::stripInlineComment($value);
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Remove surrounding quotes from a value.
     */
    private static function stripQuotes(string $value): string
    {
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            return $matches[1];
        }
        if (preg_match("/^'(.*)'$/", $value, $matches)) {
            return $matches[1];
        }
        return $value;
    }

    /**
     * Strip inline comments from unquoted values.
     */
    private static function stripInlineComment(string $value): string
    {
        // Only strip # that is preceded by a space (not inside a value)
        if (preg_match('/^(.+?)\s+#/', $value, $matches)) {
            return $matches[1];
        }
        return $value;
    }
}