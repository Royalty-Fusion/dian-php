<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Anexo Técnico DIAN — Forma de pago (cash/credit).
 *
 * Used in <cac:PaymentMeans><cbc:ID>.
 */
enum FormaPago: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case CONTADO = '1';
    case CREDITO = '2';

    public function description(): string
    {
        return match ($this) {
            self::CONTADO => 'Contado',
            self::CREDITO => 'Crédito',
        };
    }
}
