<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Helper methods reused by every backed enum that implements {@see DianCatalogInterface}.
 *
 * Provides `code()`, `tryFromCode()`, `descriptions()` and `hasCode()` without
 * polluting each enum with boilerplate.
 */
trait HasDianCatalogHelpers
{
    public function code(): string
    {
        return (string) $this->value;
    }

    public static function tryFromCode(string $code): ?self
    {
        return self::tryFrom($code);
    }

    /**
     * @return array<int|string,string>  Map of code => description (numeric codes become int keys)
     */
    public static function descriptions(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[(string) $case->value] = $case->description();
        }
        return $out;
    }

    public static function hasCode(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}
