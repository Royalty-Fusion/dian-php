<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\AllowanceCharge;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;

/**
 * CUFE/CUDE generator tests, anchored on the worked example published in the
 * DIAN Anexo Técnico (factura 323200000129).
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

    public function testOfficialAnexoTecnicoExample(): void
    {
        $invoice = (new Invoice())
            ->setPrefijo('')
            ->setNumero('323200000129')
            ->setFecha(new \DateTimeImmutable('2019-01-16T10:53:10-05:00'))
            ->setCompany((new Company())->setNit('700085371'))
            ->setClient((new Client())->setNumeroDocumento('800199436'))
            ->addItem(
                (new Item())->setCantidad(1)->setPrecio(1500000.00)->addTax(
                    (new Tax())->setCode('01')->setPercent(19)->setBase(1500000.00)->setAmount(285000.00)
                )
            );

        $this->assertSame(
            '8bb918b19ba22a694f1da11c643b5e9de39adf60311cf179179e9b33381030bcd4c3c3f156c506ed5908f9276f5bd9b4',
            (new CufeGenerator())->generate($invoice, '693ff6f2a553c3646a063436fd4dd9ded0311471', CufeGenerator::ENV_PRODUCCION)
        );
    }

    public function testCufeUsesTheSameTotalsAsTheXml(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->getItems()[0]->addAllowanceCharge(
            (new AllowanceCharge())->setChargeIndicator(false)->setAmount(5000.00)->setBaseAmount(100000.00)
        );

        $string = (new CufeGenerator())->buildString($invoice, 'k', CufeGenerator::ENV_HABILITACION);

        // ValFac = LineExtensionAmount after the line discount (95000), not cantidad × precio.
        $this->assertStringStartsWith('SETT9900000012026-05-2910:00:00-05:0095000.00', $string);
    }

    public function testIssueTimeIsNormalisedToColombianOffset(): void
    {
        $invoice = $this->makeInvoice()->setFecha(new \DateTimeImmutable('2026-05-29T15:00:00+00:00'));

        $string = (new CufeGenerator())->buildString($invoice, 'k');

        $this->assertStringContainsString('2026-05-2910:00:00-05:00', $string);
    }

    public function testCudeHasNoDocumentTypeAndUsesSoftwarePin(): void
    {
        $note = (new CreditNote())
            ->setPrefijo('NC')
            ->setNumero('1')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setCompany((new Company())->setNit('901234567'))
            ->setClient((new Client())->setNumeroDocumento('1010101010'))
            ->setSoftware((new Software())->setPin('12345'))
            ->addItem((new Item())->setCantidad(1)->setPrecio(100.00));

        $this->assertSame(
            'NC12026-05-2910:00:00-05:00100.00010.00040.00030.00100.009012345671010101010123452',
            (new CufeGenerator())->buildString($note, '12345', CufeGenerator::ENV_HABILITACION)
        );
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
