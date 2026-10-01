<?php

namespace Keel\App\Support;

/**
 * URLs for files in public_html, versioned by modification time so a browser
 * picks up a changed file.
 */
final class Asset
{
    public static function url(string $path): string
    {
        return $path . '?v=' . (int) @filemtime(dirname(__DIR__, 3) . '/public_html' . $path);
    }
}
