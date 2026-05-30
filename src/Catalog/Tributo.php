<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Códigos de impuestos / tributos".
 *
 * Used in <cac:TaxScheme><cbc:ID> and in the three CUFE tax slots
 * (CodImp1=01 IVA, CodImp2=04 INC, CodImp3=03 ICA).
 */
enum Tributo: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case IVA                  = '01';
    case IMPUESTO_CONSUMO     = '02';
    case ICA                  = '03';
    case INC                  = '04';
    case RETE_IVA             = '05';
    case RETE_FUENTE          = '06';
    case RETE_ICA             = '07';
    case BOLSA_PLASTICA       = '22';
    case AIU                  = '25';
    case IMPUESTO_CARBONO     = '34';
    case IMPUESTO_PLASTICOS   = '35';
    case NO_APLICA            = 'ZZ';

    public function description(): string
    {
        return match ($this) {
            self::IVA                => 'IVA',
            self::IMPUESTO_CONSUMO   => 'Impuesto al consumo',
            self::ICA                => 'ICA',
            self::INC                => 'INC',
            self::RETE_IVA           => 'ReteIVA',
            self::RETE_FUENTE        => 'ReteFuente',
            self::RETE_ICA           => 'ReteICA',
            self::BOLSA_PLASTICA     => 'Bolsas plásticas',
            self::AIU                => 'AIU (Administración – Imprevistos – Utilidad)',
            self::IMPUESTO_CARBONO   => 'Impuesto Nacional al Carbono',
            self::IMPUESTO_PLASTICOS => 'Impuesto Nacional sobre Productos Plásticos de Un Solo Uso',
            self::NO_APLICA          => 'No aplica',
        };
    }

    public function isRetention(): bool
    {
        return match ($this) {
            self::RETE_IVA, self::RETE_FUENTE, self::RETE_ICA => true,
            default => false,
        };
    }
}
