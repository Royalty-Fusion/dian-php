<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Tipo de Factura" (InvoiceTypeCode).
 */
enum TipoFactura: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case VENTA                     = '01';
    case EXPORTACION               = '02';
    case CONTINGENCIA_FACTURADOR    = '03';
    case CONTINGENCIA_DIAN          = '04';

    public function description(): string
    {
        return match ($this) {
            self::VENTA                  => 'Factura electrónica de Venta',
            self::EXPORTACION            => 'Factura electrónica de exportación',
            self::CONTINGENCIA_FACTURADOR => 'Factura electrónica de Venta tipo Contingencia Facturador',
            self::CONTINGENCIA_DIAN       => 'Factura electrónica de Venta tipo Contingencia DIAN',
        };
    }
}
