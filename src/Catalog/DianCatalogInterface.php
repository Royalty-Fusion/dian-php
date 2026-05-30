<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Common contract for every DIAN catalog (TipoDocumento, FormaPago, Tributo, etc.).
 *
 * Used by validators (Phase 9) and Twig templates so any code that consumes a
 * catalog can rely on the same shape: `->code()` for the raw value emitted in
 * the XML and `->description()` for the human-readable label.
 */
interface DianCatalogInterface
{
    public function code(): string;

    public function description(): string;
}
