<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Concepto de la Nota Crédito" (DiscrepancyResponse/ResponseCode).
 */
enum TipoNotaCredito: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case DEVOLUCION_PARCIAL    = '1';
    case ANULACION_TOTAL       = '2';
    case REBAJA_TOTAL          = '3';
    case DESCUENTO_TOTAL       = '4';
    case RESCISION             = '5';
    case OTROS                 = '6';

    public function description(): string
    {
        return match ($this) {
            self::DEVOLUCION_PARCIAL => 'Devolución parcial de los bienes y/o no aceptación parcial del servicio',
            self::ANULACION_TOTAL    => 'Anulación de factura electrónica',
            self::REBAJA_TOTAL       => 'Rebaja o descuento parcial o total',
            self::DESCUENTO_TOTAL    => 'Ajuste de precio',
            self::RESCISION          => 'Rescisión: nulidad por falta de requisitos',
            self::OTROS              => 'Otros',
        };
    }
}
