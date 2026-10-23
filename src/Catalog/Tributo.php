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

    case IVA                    = '01';
    case IMPUESTO_CONSUMO       = '02';
    case ICA                    = '03';
    case INC                    = '04';
    case RETE_IVA               = '05';
    case RETE_FUENTE            = '06';
    case RETE_ICA               = '07';
    case IC_PORCENTUAL          = '08';
    case FONDO_HORTICULTURA     = '20';
    case TIMBRE                 = '21';
    case BOLSA_PLASTICA         = '22';
    case IMPUESTO_CARBONO       = '23';
    case IMPUESTO_COMBUSTIBLES  = '24';
    case SOBRETASA_COMBUSTIBLES = '25';
    case SORDICOM               = '26';
    case CONSUMO_DATOS          = '30';
    case ICL                    = '32';
    case IMPUESTO_PLASTICOS     = '33';
    case IBUA                   = '34';
    case ICUI                   = '35';
    case ADV                    = '36';
    case IVA_E_INC              = 'ZA';
    case NO_APLICA              = 'ZZ';

    public function description(): string
    {
        return match ($this) {
            self::IVA                    => 'Impuesto sobre las Ventas',
            self::IMPUESTO_CONSUMO       => 'Impuesto al Consumo Departamental Nominal',
            self::ICA                    => 'Impuesto de Industria, Comercio y Aviso',
            self::INC                    => 'Impuesto Nacional al Consumo',
            self::RETE_IVA               => 'Retención sobre el IVA',
            self::RETE_FUENTE            => 'Retención sobre Renta',
            self::RETE_ICA               => 'Retención sobre el ICA',
            self::IC_PORCENTUAL          => 'Impuesto al Consumo Departamental Porcentual',
            self::FONDO_HORTICULTURA     => 'Cuota de Fomento Hortifrutícula',
            self::TIMBRE                 => 'Impuesto de Timbre',
            self::BOLSA_PLASTICA         => 'Impuesto Nacional al Consumo de Bolsa Plástica',
            self::IMPUESTO_CARBONO       => 'Impuesto Nacional del Carbono',
            self::IMPUESTO_COMBUSTIBLES  => 'Impuesto Nacional a los Combustibles',
            self::SOBRETASA_COMBUSTIBLES => 'Sobretasa a los combustibles',
            self::SORDICOM               => 'Contribución minoristas (Combustibles)',
            self::CONSUMO_DATOS          => 'Impuesto al Consumo de Datos',
            self::ICL                    => 'Impuesto al Consumo de Licores',
            self::IMPUESTO_PLASTICOS     => 'Impuesto Nacional sobre Productos Plásticos de un solo uso',
            self::IBUA                   => 'Impuesto a las Bebidas Ultraprocesadas Azucaradas',
            self::ICUI                   => 'Impuesto a los Comestibles Ultraprocesados',
            self::ADV                    => 'Impuesto Ad Valorem',
            self::IVA_E_INC              => 'IVA e INC',
            self::NO_APLICA              => 'No aplica',
        };
    }

    /** Value for cac:TaxScheme/cbc:Name (DIAN tabla 13.2.6.2). */
    public function schemeName(): string
    {
        return match ($this) {
            self::IVA                    => 'IVA',
            self::IMPUESTO_CONSUMO       => 'IC',
            self::ICA                    => 'ICA',
            self::INC                    => 'INC',
            self::RETE_IVA               => 'ReteIVA',
            self::RETE_FUENTE            => 'ReteRenta',
            self::RETE_ICA               => 'ReteICA',
            self::IC_PORCENTUAL          => 'IC Porcentual',
            self::FONDO_HORTICULTURA     => 'FtoHorticultura',
            self::TIMBRE                 => 'Timbre',
            self::BOLSA_PLASTICA         => 'INC Bolsas',
            self::IMPUESTO_CARBONO       => 'INCarbono',
            self::IMPUESTO_COMBUSTIBLES  => 'INCombustibles',
            self::SOBRETASA_COMBUSTIBLES => 'Sobretasa Combustibles',
            self::SORDICOM               => 'Sordicom',
            self::CONSUMO_DATOS          => 'Impuesto al Consumo de Datos',
            self::ICL                    => 'ICL',
            self::IMPUESTO_PLASTICOS     => 'INPP',
            self::IBUA                   => 'IBUA',
            self::ICUI                   => 'ICUI',
            self::ADV                    => 'ADV',
            self::IVA_E_INC              => 'IVA e INC',
            self::NO_APLICA              => 'No aplica',
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
