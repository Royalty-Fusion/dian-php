<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Códigos DANE — Departamentos de Colombia (2 dígitos).
 *
 * Emitted under <cac:Address><cbc:CountrySubentityCode>.
 */
enum Departamento: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case AMAZONAS               = '91';
    case ANTIOQUIA              = '05';
    case ARAUCA                 = '81';
    case ATLANTICO              = '08';
    case BOLIVAR                = '13';
    case BOYACA                 = '15';
    case CALDAS                 = '17';
    case CAQUETA                = '18';
    case CASANARE               = '85';
    case CAUCA                  = '19';
    case CESAR                  = '20';
    case CHOCO                  = '27';
    case CORDOBA                = '23';
    case CUNDINAMARCA           = '25';
    case GUAINIA                = '94';
    case GUAVIARE               = '95';
    case HUILA                  = '41';
    case LA_GUAJIRA             = '44';
    case MAGDALENA              = '47';
    case META                   = '50';
    case NARINO                 = '52';
    case NORTE_DE_SANTANDER     = '54';
    case PUTUMAYO               = '86';
    case QUINDIO                = '63';
    case RISARALDA              = '66';
    case SAN_ANDRES             = '88';
    case SANTANDER              = '68';
    case SUCRE                  = '70';
    case TOLIMA                 = '73';
    case VALLE_DEL_CAUCA        = '76';
    case VAUPES                 = '97';
    case VICHADA                = '99';
    case BOGOTA                 = '11';

    public function description(): string
    {
        return match ($this) {
            self::AMAZONAS            => 'Amazonas',
            self::ANTIOQUIA           => 'Antioquia',
            self::ARAUCA              => 'Arauca',
            self::ATLANTICO           => 'Atlántico',
            self::BOLIVAR             => 'Bolívar',
            self::BOYACA              => 'Boyacá',
            self::CALDAS              => 'Caldas',
            self::CAQUETA             => 'Caquetá',
            self::CASANARE            => 'Casanare',
            self::CAUCA               => 'Cauca',
            self::CESAR               => 'Cesar',
            self::CHOCO               => 'Chocó',
            self::CORDOBA             => 'Córdoba',
            self::CUNDINAMARCA        => 'Cundinamarca',
            self::GUAINIA             => 'Guainía',
            self::GUAVIARE            => 'Guaviare',
            self::HUILA               => 'Huila',
            self::LA_GUAJIRA          => 'La Guajira',
            self::MAGDALENA           => 'Magdalena',
            self::META                => 'Meta',
            self::NARINO              => 'Nariño',
            self::NORTE_DE_SANTANDER  => 'Norte de Santander',
            self::PUTUMAYO            => 'Putumayo',
            self::QUINDIO             => 'Quindío',
            self::RISARALDA           => 'Risaralda',
            self::SAN_ANDRES          => 'Archipiélago de San Andrés, Providencia y Santa Catalina',
            self::SANTANDER           => 'Santander',
            self::SUCRE               => 'Sucre',
            self::TOLIMA              => 'Tolima',
            self::VALLE_DEL_CAUCA     => 'Valle del Cauca',
            self::VAUPES              => 'Vaupés',
            self::VICHADA             => 'Vichada',
            self::BOGOTA              => 'Bogotá, D.C.',
        };
    }
}
