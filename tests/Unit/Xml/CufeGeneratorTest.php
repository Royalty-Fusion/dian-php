<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;

/**
 * Sanity tests for the CUFE/CUDE generator. These are NOT the official
 * golden-master tests — Phase 13 will add fixtures from the DIAN sample set.
 *
 * For now we only verify determinism and the SHA-384 length (96 hex chars).
 */
final class CufeGeneratorTest extends TestCase
{
    public function testCufeIsDeterministicAndHasExpectedLength(): void
    {
        $invoice = $this->makeInvoice();
        $gen     = new CufeGenerator();

        $cufeA = $gen->generate($invoice, 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c', CufeGenerator::ENV_HABILITACION);
        $cufeB = $gen->generate($invoice, 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c', CufeGenerator::ENV_HABILITACION);

        $this->assertSame($cufeA, $cufeB, 'CUFE must be deterministic for the same inputs.');
        $this->assertSame(96, strlen($cufeA), 'CUFE must be a SHA-384 hex string of 96 characters.');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $cufeA);
    }

    public function testEnvironmentFlagAffectsCufe(): void
    {
        $invoice = $this->makeInvoice();
        $gen     = new CufeGenerator();

        $cufeHab  = $gen->generate($invoice, 'k', CufeGenerator::ENV_HABILITACION);
        $cufeProd = $gen->generate($invoice, 'k', CufeGenerator::ENV_PRODUCCION);

        $this->assertNotSame($cufeHab, $cufeProd, 'CUFE must include the environment flag.');
    }

    private function makeInvoice(): Invoice
    {
        $company = (new Company())->setNit('901234567')->setRazonSocial('Test S.A.S');
        $client  = (new Client())->setNumeroDocumento('1010101010')->setRazonSocial('Cliente Demo');

        $tax = (new Tax())
            ->setCode('01')
            ->setName('IVA')
            ->setPercent(19.00)
            ->setBase(100000.00)
            ->setAmount(19000.00);

        $item = (new Item())
            ->setDescripcion('Producto Demo')
            ->setCantidad(1.0)
            ->setPrecio(100000.00)
            ->addTax($tax);

        return (new Invoice())
            ->setPrefijo('SETT')
            ->setNumero('990000001')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setCompany($company)
            ->setClient($client)
            ->setTotal(119000.00)
            ->addItem($item)
            ->addTax($tax);
    }
}
