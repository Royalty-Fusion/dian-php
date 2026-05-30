<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Responsabilidades fiscales" (Casilla 53 RUT).
 *
 * Emitted joined by ';' inside <sts:DianExtensions> at company level and in
 * the customer party block when applicable.
 */
enum Responsabilidad: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case GRAN_CONTRIBUYENTE                = 'O-13';
    case AUTORRETENEDOR                    = 'O-15';
    case AGENTE_RETENCION_IVA              = 'O-23';
    case REGIMEN_SIMPLE                    = 'O-47';
    case NO_RESPONSABLE_IVA                = 'R-99-PN';
    case IMPUESTO_NACIONAL_CONSUMO         = 'O-48';
    case IMPUESTO_VENTAS_BIENES_CORPORALES = 'O-49';

    public function description(): string
    {
        return match ($this) {
            self::GRAN_CONTRIBUYENTE                => 'Gran contribuyente',
            self::AUTORRETENEDOR                    => 'Autorretenedor',
            self::AGENTE_RETENCION_IVA              => 'Agente de retención IVA',
            self::REGIMEN_SIMPLE                    => 'Régimen Simple de Tributación – SIMPLE',
            self::NO_RESPONSABLE_IVA                => 'No responsable',
            self::IMPUESTO_NACIONAL_CONSUMO         => 'Impuesto Nacional al Consumo',
            self::IMPUESTO_VENTAS_BIENES_CORPORALES => 'Impuesto sobre las ventas — IVA',
        };
    }
}
