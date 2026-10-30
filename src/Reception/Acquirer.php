<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Reception;

/**
 * Adquiriente registered in the DIAN (GetAcquirer): legal name and the email
 * where it receives electronic invoices.
 */
final class Acquirer
{
    public function __construct(
        public readonly string $identificationType,
        public readonly string $identificationNumber,
        public readonly string $name,
        public readonly string $email,
    ) {
    }
}
