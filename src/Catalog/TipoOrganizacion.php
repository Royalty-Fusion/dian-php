<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Tabla "Tipo de Organización".
 *
 * Used in <cbc:AdditionalAccountID>.
 */
enum TipoOrganizacion: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case PERSONA_JURIDICA = '1';
    case PERSONA_NATURAL  = '2';

    public function description(): string
    {
        return match ($this) {
            self::PERSONA_JURIDICA => 'Persona Jurídica y asimiladas',
            self::PERSONA_NATURAL  => 'Persona Natural y asimiladas',
        };
    }
}
