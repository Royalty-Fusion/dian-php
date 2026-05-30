<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Régimen Fiscal".
 *
 * Used in <cbc:TaxLevelCode listName="..."> nested under PartyTaxScheme.
 */
enum TipoRegimen: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    /** Responsable de IVA */
    case RESPONSABLE_IVA = '48';
    /** No responsable de IVA */
    case NO_RESPONSABLE_IVA = '49';

    public function description(): string
    {
        return match ($this) {
            self::RESPONSABLE_IVA    => 'Responsable de IVA',
            self::NO_RESPONSABLE_IVA => 'No Responsable de IVA',
        };
    }
}
