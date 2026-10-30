<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Documentos equivalentes electrónicos (Resolución DIAN 000165 de 2023) and
 * their notas de ajuste. The value is the cbc:InvoiceTypeCode / CreditNoteTypeCode.
 */
enum TipoDocumentoEquivalente: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case POS                      = '20';
    case CINE                     = '25';
    case ESPECTACULOS_PUBLICOS    = '27';
    case JUEGOS                   = '30';
    case TRANSPORTE_TERRESTRE     = '35';
    case PEAJES                   = '40';
    case EXTRACTO_FINANCIERO      = '45';
    case TRANSPORTE_AEREO         = '50';
    case BOLSA_VALORES            = '55';
    case SERVICIOS_PUBLICOS       = '60';
    case NOTA_AJUSTE_DEBITO       = '93';
    case NOTA_AJUSTE_CREDITO      = '94';

    public function description(): string
    {
        return match ($this) {
            self::POS                   => 'Documento equivalente electrónico del tiquete de máquina registradora con sistema P.O.S.',
            self::CINE                  => 'Boleta de ingreso a cine',
            self::ESPECTACULOS_PUBLICOS => 'Boleta de ingreso a espectáculos públicos',
            self::JUEGOS                => 'Documento en juegos localizados y no localizados - relación diaria de control de ventas',
            self::TRANSPORTE_TERRESTRE  => 'Tiquete de transporte de pasajeros terrestre',
            self::PEAJES                => 'Documento expedido para el cobro de peajes',
            self::EXTRACTO_FINANCIERO   => 'Extracto expedido por sociedades financieras y fondos',
            self::TRANSPORTE_AEREO      => 'Tiquete de billete de transporte aéreo de pasajeros',
            self::BOLSA_VALORES         => 'Documento de operación de bolsa de valores, agropecuaria y de otros commodities',
            self::SERVICIOS_PUBLICOS    => 'Documento expedido para los servicios públicos y domiciliarios',
            self::NOTA_AJUSTE_DEBITO    => 'Nota de ajuste de tipo débito al documento equivalente',
            self::NOTA_AJUSTE_CREDITO   => 'Nota de ajuste de tipo crédito al documento equivalente',
        };
    }
}
