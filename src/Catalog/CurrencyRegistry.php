<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Full ISO 4217 currency registry (~180 codes).
 *
 * Loaded from resources/catalogs/type_currencies.csv. Complements the small
 * {@see Moneda} enum.
 */
final class CurrencyRegistry
{
    /** @var array<string,string>|null  code => name */
    private static ?array $data = null;

    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        self::$data = [];
        $file = __DIR__ . '/../../resources/catalogs/type_currencies.csv';
        if (is_file($file)) {
            $h = fopen($file, 'r');
            if ($h !== false) {
                while (($row = fgetcsv($h, 0, "\t")) !== false) {
                    if (count($row) >= 3) {
                        self::$data[trim($row[2])] = trim($row[1]);
                    }
                }
                fclose($h);
            }
        }
        return self::$data;
    }

    public static function name(string $code): ?string
    {
        return self::load()[$code] ?? null;
    }

    public static function has(string $code): bool
    {
        return isset(self::load()[$code]);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::load();
    }
}
