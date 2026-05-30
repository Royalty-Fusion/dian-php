<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

/**
 * One validation finding. Mirrors the structure of DIAN's b:ErrorMessage
 * so the same renderer can be used for both pre-send and post-send errors.
 */
final class ValidationError
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly string $xpath = '',
        public readonly string $severity = 'error', // error | warning
    ) {
    }
}
