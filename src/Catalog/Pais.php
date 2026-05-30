<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * ISO 3166-1 alpha-2 country codes — subset used in Colombian invoicing.
 *
 * Emitted under <cac:Address><cac:Country><cbc:IdentificationCode>.
 */
enum Pais: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case COLOMBIA       = 'CO';
    case ESTADOS_UNIDOS = 'US';
    case MEXICO         = 'MX';
    case BRASIL         = 'BR';
    case PERU           = 'PE';
    case ARGENTINA      = 'AR';
    case CHILE          = 'CL';
    case ECUADOR        = 'EC';
    case VENEZUELA      = 'VE';
    case ESPANA         = 'ES';
    case CANADA         = 'CA';
    case PANAMA         = 'PA';

    public function description(): string
    {
        return match ($this) {
            self::COLOMBIA       => 'Colombia',
            self::ESTADOS_UNIDOS => 'Estados Unidos de América',
            self::MEXICO         => 'México',
            self::BRASIL         => 'Brasil',
            self::PERU           => 'Perú',
            self::ARGENTINA      => 'Argentina',
            self::CHILE          => 'Chile',
            self::ECUADOR        => 'Ecuador',
            self::VENEZUELA      => 'Venezuela (República Bolivariana de)',
            self::ESPANA         => 'España',
            self::CANADA         => 'Canadá',
            self::PANAMA         => 'Panamá',
        };
    }
}
