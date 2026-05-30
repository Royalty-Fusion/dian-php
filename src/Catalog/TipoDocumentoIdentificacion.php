<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Tipo de Documento de Identificación".
 *
 * Used in <cbc:CompanyID schemeName="..."> for both supplier and customer
 * parties. The code itself is emitted as the value of the schemeName attribute.
 */
enum TipoDocumentoIdentificacion: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case REGISTRO_CIVIL                 = '11';
    case TARJETA_IDENTIDAD              = '12';
    case CEDULA_CIUDADANIA              = '13';
    case TARJETA_EXTRANJERIA            = '21';
    case CEDULA_EXTRANJERIA             = '22';
    case NIT                            = '31';
    case PASAPORTE                      = '41';
    case DOCUMENTO_EXTRANJERO           = '42';
    case PEP                            = '47';
    case PPT                            = '48';
    case NIT_OTRO_PAIS                  = '50';
    case NUIP                           = '91';

    public function description(): string
    {
        return match ($this) {
            self::REGISTRO_CIVIL        => 'Registro civil',
            self::TARJETA_IDENTIDAD     => 'Tarjeta de identidad',
            self::CEDULA_CIUDADANIA     => 'Cédula de ciudadanía',
            self::TARJETA_EXTRANJERIA   => 'Tarjeta de extranjería',
            self::CEDULA_EXTRANJERIA    => 'Cédula de extranjería',
            self::NIT                   => 'NIT',
            self::PASAPORTE             => 'Pasaporte',
            self::DOCUMENTO_EXTRANJERO  => 'Documento de identificación extranjero',
            self::PEP                   => 'PEP (Permiso Especial de Permanencia)',
            self::PPT                   => 'PPT (Permiso por Protección Temporal)',
            self::NIT_OTRO_PAIS         => 'NIT de otro país',
            self::NUIP                  => 'NUIP',
        };
    }
}
