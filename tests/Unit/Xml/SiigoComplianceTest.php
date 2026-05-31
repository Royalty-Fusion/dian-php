<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Catalog\MedioPago;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\ExchangeRate;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

/**
 * Compliance checks derived from a real Siigo invoice (resources/fixtures/
 * siigo-blindacces-FV5.xml). Each assertion targets a divergence we
 * detected between our SDK output and that real-world XML.
 */
final class SiigoComplianceTest extends TestCase
{
    public function testUuidSchemeIdDefaultsTo1MatchingSiigo(): void
    {
        $xml = (new XmlBuilder())->build($this->buildExportInvoice(), str_repeat('a', 96), 'https://example.test/qr');
        $this->assertStringContainsString('<cbc:UUID schemeID="1" schemeName="CUFE-SHA384">', $xml);
    }

    public function testUuidSchemeIdCanBeForcedTo2(): void
    {
        $xml = (new XmlBuilder(null, '2', '2'))->build($this->buildExportInvoice(), str_repeat('a', 96), 'https://example.test/qr');
        $this->assertStringContainsString('<cbc:UUID schemeID="2" schemeName="CUFE-SHA384">', $xml);
    }

    public function testRoundingAmountIsEmittedInsideTaxTotal(): void
    {
        $xml = (new XmlBuilder())->build($this->buildExportInvoice(), str_repeat('a', 96), 'https://example.test/qr');
        $this->assertStringContainsString('<cbc:RoundingAmount currencyID="COP">0.00</cbc:RoundingAmount>', $xml);
    }

    public function testPaymentExchangeRateBlockIsRenderedWhenSet(): void
    {
        $invoice = $this->buildExportInvoice();
        $invoice->setPaymentExchangeRate(
            (new ExchangeRate())
                ->setSourceCurrencyCode('COP')
                ->setSourceCurrencyBaseRate(3669.96)
                ->setTargetCurrencyCode('USD')
                ->setTargetCurrencyBaseRate(1.00)
                ->setCalculationRate(3669.96)
                ->setDate(new \DateTimeImmutable('2026-03-31'))
        );

        $xml = (new XmlBuilder())->build($invoice, str_repeat('a', 96), 'https://example.test/qr');

        $this->assertStringContainsString('<cac:PaymentExchangeRate>', $xml);
        $this->assertStringContainsString('<cbc:SourceCurrencyCode>COP</cbc:SourceCurrencyCode>', $xml);
        $this->assertStringContainsString('<cbc:TargetCurrencyCode>USD</cbc:TargetCurrencyCode>', $xml);
        $this->assertStringContainsString('<cbc:CalculationRate>3669.96</cbc:CalculationRate>', $xml);
        $this->assertStringContainsString('<cbc:Date>2026-03-31</cbc:Date>', $xml);
    }

    public function testMedioPagoOtroIsAvailable(): void
    {
        $this->assertTrue(MedioPago::hasCode('ZZZ'));
        $this->assertSame('Otro / Sin definir', MedioPago::OTRO->description());
    }

    private function buildExportInvoice(): Invoice
    {
        $tax = (new Tax())->setCode('01')->setName('IVA')->setPercent(0.0)
            ->setBase(74390089.20)->setAmount(0.0);

        $item = (new Item())
            ->setDescripcion('HORNO DE CURVADO')
            ->setCantidad(1.0)
            ->setPrecio(74390089.20)
            ->setCode('HOR-CUR-001')
            ->addTax($tax);

        return (new Invoice())
            ->setPrefijo('FV')->setNumero('5')
            ->setFecha(new \DateTimeImmutable('2026-03-31T10:14:00-05:00'))
            ->setCompany((new Company())->setNit('901944237')->setRazonSocial('BLINDACCES SAS')->setTipoDocumento('31'))
            ->setClient((new Client())->setTipoDocumento('50')->setNumeroDocumento('117750735')->setRazonSocial('BLINDACCES SA'))
            ->setTotal(74390089.20)
            ->addItem($item)
            ->addTax($tax)
            ->addPayment((new Payment())->setMethodId('2')->setMeansId(MedioPago::OTRO->code()));
    }
}
