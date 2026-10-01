<?php

namespace Keel\App\Support;

/**
 * Read access to config/unsilenced.php with dot paths.
 *
 *   Config::get('help.hotline_display')
 *   Config::get('clery.offense_columns')
 *
 * The file is loaded once per process. Tests can swap values with set() and
 * put the file back with reset().
 */
class Config
{
    private static ?array $values = null;

    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::all();

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $path, mixed $newValue): void
    {
        self::all();

        $target = &self::$values;
        foreach (explode('.', $path) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $newValue;
    }

    public static function reset(): void
    {
        self::$values = null;
    }

    private static function all(): array
    {
        if (self::$values === null) {
            self::$values = require dirname(__DIR__, 3) . '/config/unsilenced.php';
        }

        return self::$values;
    }

    /** The 50 states and DC: the codes that have a state page. */
    public static function states(): array
    {
        return (array) self::get('states', []);
    }

    /** Every code a school may carry: states, DC and territories. */
    public static function allJurisdictions(): array
    {
        return self::states() + (array) self::get('territories', []);
    }

    public static function jurisdictionName(string $code): ?string
    {
        return self::allJurisdictions()[strtoupper($code)] ?? null;
    }
}
