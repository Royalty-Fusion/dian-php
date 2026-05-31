<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Full DIAN "Responsabilidades fiscales" registry (~100 codes).
 *
 * The {@see Responsabilidad} enum only carries the 7 most common cases for
 * type safety. This registry holds the full official list (loaded from
 * resources/catalogs/type_liabilities.csv) so consumers can resolve any
 * historical or rarely-used code.
 */
final class ResponsabilidadRegistry
{
    /** @var array<string,string>|null  code => name */
    private static ?array $data = null;

    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        self::$data = [];
        $file = __DIR__ . '/../../resources/catalogs/type_liabilities.csv';
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
