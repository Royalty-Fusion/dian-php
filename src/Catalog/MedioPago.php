<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * UN/CEFACT code list 4461 — Means of Payment.
 *
 * DIAN uses this list verbatim in <cbc:PaymentMeansCode>.
 * This enum includes the most common codes used in Colombian invoicing.
 * Full list available at https://www.unece.org/cefact/codesfortrade/codes_index.html
 */
enum MedioPago: string implements DianCatalogInterface
{
    use HasDianCatalogHelpers;

    case INSTRUMENTO_NO_DEFINIDO        = '1';
    case CREDITO_ACH                    = '2';
    case DEBITO_ACH                     = '3';
    case TARJETA_DEBITO                 = '9';
    case EFECTIVO                       = '10';
    case CHEQUE                         = '20';
    case TRANSFERENCIA_CREDITO          = '31';
    case CONSIGNACION_CUENTA            = '42';
    case TARJETA_CREDITO                = '48';
    case DEBITO_CUENTA_BANCARIA         = '49';
    case PAGO_POR_INSTRUCCION           = '50';
    case CUPON                          = '67';
    case BANCARIO_INTERNO               = '70';
    case COMPENSACION                   = '97';

    public function description(): string
    {
        return match ($this) {
            self::INSTRUMENTO_NO_DEFINIDO  => 'Instrumento no definido',
            self::CREDITO_ACH              => 'Crédito ACH',
            self::DEBITO_ACH               => 'Débito ACH',
            self::TARJETA_DEBITO           => 'Tarjeta débito',
            self::EFECTIVO                 => 'Efectivo',
            self::CHEQUE                   => 'Cheque',
            self::TRANSFERENCIA_CREDITO    => 'Transferencia crédito',
            self::CONSIGNACION_CUENTA      => 'Consignación cuenta',
            self::TARJETA_CREDITO          => 'Tarjeta crédito',
            self::DEBITO_CUENTA_BANCARIA   => 'Débito cuenta bancaria',
            self::PAGO_POR_INSTRUCCION     => 'Pago por instrucción',
            self::CUPON                    => 'Cupón',
            self::BANCARIO_INTERNO         => 'Bancario interno',
            self::COMPENSACION             => 'Compensación de deudas',
        };
    }
}
