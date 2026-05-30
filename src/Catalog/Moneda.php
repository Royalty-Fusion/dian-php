<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * ISO 4217 currency codes — DIAN accepts every ISO 4217 code in
 * <cbc:DocumentCurrencyCode>. This enum captures the ones most used in
 * Colombian invoicing; pass any other ISO 4217 code directly to the model.
 */
enum Moneda: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case COP = 'COP';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case MXN = 'MXN';
    case BRL = 'BRL';
    case PEN = 'PEN';
    case ARS = 'ARS';
    case CLP = 'CLP';
    case CAD = 'CAD';
    case JPY = 'JPY';
    case CNY = 'CNY';

    public function description(): string
    {
        return match ($this) {
            self::COP => 'Peso colombiano',
            self::USD => 'Dólar estadounidense',
            self::EUR => 'Euro',
            self::GBP => 'Libra esterlina',
            self::MXN => 'Peso mexicano',
            self::BRL => 'Real brasileño',
            self::PEN => 'Sol peruano',
            self::ARS => 'Peso argentino',
            self::CLP => 'Peso chileno',
            self::CAD => 'Dólar canadiense',
            self::JPY => 'Yen japonés',
            self::CNY => 'Yuan chino',
        };
    }
}
