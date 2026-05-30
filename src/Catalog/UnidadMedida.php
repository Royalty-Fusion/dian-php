<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * UN/ECE Recommendation 20 — Codes for Units of Measure used in International Trade.
 *
 * Emitted as the `unitCode` attribute of <cbc:InvoicedQuantity>,
 * <cbc:CreditedQuantity> and <cbc:DebitedQuantity>.
 *
 * The list is huge (>1000 codes). This enum captures the most common ones in
 * Colombian invoicing; consumers can pass any string code directly to the model
 * — the enum is just a typed alias and a description helper.
 */
enum UnidadMedida: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case UNIDAD                = 'EA';
    case CARGA_UNITARIA        = '94';
    case KILOGRAMO             = 'KGM';
    case GRAMO                 = 'GRM';
    case TONELADA              = 'TNE';
    case LITRO                 = 'LTR';
    case METRO                 = 'MTR';
    case METRO_CUADRADO        = 'MTK';
    case METRO_CUBICO          = 'MTQ';
    case CENTIMETRO            = 'CMT';
    case CAJA                  = 'BX';
    case PAQUETE               = 'PK';
    case HORA                  = 'HUR';
    case DIA                   = 'DAY';
    case MES                   = 'MON';
    case GALON_US              = 'GLL';
    case GLOBAL                = 'WSD';
    case SERVICIO              = 'ZZ';

    public function description(): string
    {
        return match ($this) {
            self::UNIDAD           => 'Unidad',
            self::CARGA_UNITARIA   => 'Carga unitaria',
            self::KILOGRAMO        => 'Kilogramo',
            self::GRAMO            => 'Gramo',
            self::TONELADA         => 'Tonelada métrica',
            self::LITRO            => 'Litro',
            self::METRO            => 'Metro',
            self::METRO_CUADRADO   => 'Metro cuadrado',
            self::METRO_CUBICO     => 'Metro cúbico',
            self::CENTIMETRO       => 'Centímetro',
            self::CAJA             => 'Caja',
            self::PAQUETE          => 'Paquete',
            self::HORA             => 'Hora',
            self::DIA              => 'Día',
            self::MES              => 'Mes',
            self::GALON_US         => 'Galón (US)',
            self::GLOBAL           => 'Global',
            self::SERVICIO         => 'Servicio',
        };
    }
}
