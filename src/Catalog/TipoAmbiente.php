<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Ambientes de servicio".
 *
 * Used in <cbc:ProfileExecutionID> and in the CUFE/CUDE concatenation string.
 */
enum TipoAmbiente: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case PRODUCCION   = '1';
    case HABILITACION = '2';

    public function description(): string
    {
        return match ($this) {
            self::PRODUCCION   => 'Producción',
            self::HABILITACION => 'Habilitación',
        };
    }
}
