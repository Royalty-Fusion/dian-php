<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\NitDvCalculator;

/**
 * Verification digit golden values, taken from public NITs on RUES and the
 * DIAN test set. These have been cross-checked with the official online
 * "Consulta Estado RUT" calculator.
 */
final class NitDvCalculatorTest extends TestCase
{
    /** @return array<string,array{0:string,1:int}> */
    public static function provider(): array
    {
        return [
            'DIAN itself'    => ['800197268', 4],
            'Bancolombia'    => ['890903938', 8],
            'Ecopetrol'      => ['899999068', 1],
            'Avianca'        => ['890100577', 6],
            'Empty input'    => ['', 0],
        ];
    }

    /** @dataProvider provider */
    public function testComputeMatchesDianGoldenValues(string $nit, int $expected): void
    {
        $this->assertSame($expected, NitDvCalculator::compute($nit));
    }

    public function testStripsNonDigits(): void
    {
        $this->assertSame(
            NitDvCalculator::compute('800197268'),
            NitDvCalculator::compute('800.197.268')
        );
    }
}
