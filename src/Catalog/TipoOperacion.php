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

    case ESTANDAR                       = '10';
    case AIU                            = '11';
    case MANDATARIOS                    = '12';
    case TRANSPORTE                     = '13';
    case CAMBIARIO                      = '14';
    case NOTARIOS                       = '15';
    case COMPRA_DIVISAS                 = '16';
    case VENTA_DIVISAS                  = '17';
    case NOTA_CREDITO_REFERENCIA        = '20';
    case NOTA_CREDITO_SIN_REFERENCIA    = '22';
    case NOTA_DEBITO_REFERENCIA         = '30';
    case NOTA_DEBITO_SIN_REFERENCIA     = '32';

    public function description(): string
    {
        return match ($this) {
            self::ESTANDAR                    => 'Estándar',
            self::AIU                         => 'Administración, Imprevistos y Utilidad – AIU',
            self::MANDATARIOS                 => 'Mandatos',
            self::TRANSPORTE                  => 'Transporte',
            self::CAMBIARIO                   => 'Cambiario',
            self::NOTARIOS                    => 'Notarios',
            self::COMPRA_DIVISAS              => 'Compra de divisas',
            self::VENTA_DIVISAS               => 'Venta de divisas',
            self::NOTA_CREDITO_REFERENCIA     => 'Nota crédito que referencia una factura electrónica',
            self::NOTA_CREDITO_SIN_REFERENCIA => 'Nota crédito sin referencia a facturas',
            self::NOTA_DEBITO_REFERENCIA      => 'Nota débito que referencia una factura electrónica',
            self::NOTA_DEBITO_SIN_REFERENCIA  => 'Nota débito sin referencia a facturas',
        };
    }
}
