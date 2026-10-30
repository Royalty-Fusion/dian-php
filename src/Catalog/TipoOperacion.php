<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Tipo de Operación" para Factura electrónica de Venta.
 *
 * Emitted as <cbc:CustomizationID> at document level.
 */
enum TipoOperacion: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case AIU                            = '09';
    case ESTANDAR                       = '10';
    case MANDATOS                       = '11';
    case TRANSPORTE                     = '12';
    case CAMBIARIO                      = '13';
    case NOTARIOS                       = '14';
    case COMPRA_DIVISAS                 = '15';
    case VENTA_DIVISAS                  = '16';
    case NOTA_CREDITO_REFERENCIA        = '20';
    case NOTA_CREDITO_SIN_REFERENCIA    = '22';
    case NOTA_CREDITO_FACTURACION_V1    = '23';
    case NOTA_DEBITO_REFERENCIA         = '30';
    case NOTA_DEBITO_SIN_REFERENCIA     = '32';
    case NOTA_DEBITO_FACTURACION_V1     = '33';

    /** @deprecated use MANDATOS */
    public const MANDATARIOS = self::MANDATOS;

    public function description(): string
    {
        return match ($this) {
            self::ESTANDAR                    => 'Estándar',
            self::AIU                         => 'Administración, Imprevistos y Utilidad – AIU',
            self::MANDATOS                    => 'Mandatos',
            self::TRANSPORTE                  => 'Transporte',
            self::CAMBIARIO                   => 'Cambiario',
            self::NOTARIOS                    => 'Notarios',
            self::COMPRA_DIVISAS              => 'Compra de divisas',
            self::VENTA_DIVISAS               => 'Venta de divisas',
            self::NOTA_CREDITO_REFERENCIA     => 'Nota crédito que referencia una factura electrónica',
            self::NOTA_CREDITO_SIN_REFERENCIA => 'Nota crédito sin referencia a facturas',
            self::NOTA_DEBITO_REFERENCIA      => 'Nota débito que referencia una factura electrónica',
            self::NOTA_DEBITO_SIN_REFERENCIA  => 'Nota débito sin referencia a facturas',
            self::NOTA_CREDITO_FACTURACION_V1 => 'Nota crédito para facturación electrónica V1 (Decreto 2242)',
            self::NOTA_DEBITO_FACTURACION_V1  => 'Nota débito para facturación electrónica V1 (Decreto 2242)',
        };
    }
}
