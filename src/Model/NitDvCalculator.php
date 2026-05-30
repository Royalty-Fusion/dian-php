<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * Calculator for the DIAN Dígito de Verificación (DV) of a NIT.
 *
 * Mod-11 algorithm with the DIAN-published prime factor list (Anexo Técnico
 * Apéndice DV). Result is a single digit 0-9 emitted as the schemeID attribute
 * of <cbc:CompanyID>.
 */
final class NitDvCalculator
{
    /** @var int[] DIAN factor list, indexed right-to-left */
    private const FACTORS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    public static function compute(string $nit): int
    {
        $digits = preg_replace('/[^0-9]/', '', $nit) ?? '';
        if ($digits === '') {
            return 0;
        }

        $sum = 0;
        $len = strlen($digits);
        for ($i = 0; $i < $len; $i++) {
            $digit  = (int) $digits[$len - 1 - $i];
            $factor = self::FACTORS[$i] ?? 0;
            $sum   += $digit * $factor;
        }

        $rest = $sum % 11;
        if ($rest >= 2) {
            return 11 - $rest;
        }
        return $rest;
    }
}
