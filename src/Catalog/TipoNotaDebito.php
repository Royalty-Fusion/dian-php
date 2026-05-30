<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Concepto de la Nota Débito" (DiscrepancyResponse/ResponseCode).
 */
enum TipoNotaDebito: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case INTERESES         = '1';
    case GASTOS_POR_COBRAR = '2';
    case CAMBIO_DEL_VALOR  = '3';
    case OTROS             = '4';

    public function description(): string
    {
        return match ($this) {
            self::INTERESES         => 'Intereses',
            self::GASTOS_POR_COBRAR => 'Gastos por cobrar',
            self::CAMBIO_DEL_VALOR  => 'Cambio del valor',
            self::OTROS             => 'Otros',
        };
    }
}
