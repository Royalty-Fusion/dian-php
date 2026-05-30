<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Report;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Report\HtmlReport;

final class HtmlReportTest extends TestCase
{
    public function testRenderProducesHtmlWithDocumentData(): void
    {
        $report = new HtmlReport();
        $html   = $report->render($this->makeInvoice(), str_repeat('a', 96), 'https://example.test/qr');

        $this->assertStringContainsString('Factura Electrónica de Venta', $html);
        $this->assertStringContainsString('SETT990000001', $html);
        $this->assertStringContainsString('Royalty Fusion S.A.S', $html);
        $this->assertStringContainsString('Juan Pérez', $html);
        $this->assertStringContainsString('Total a pagar', $html);
        $this->assertStringContainsString('CUFE:', $html);
        $this->assertStringContainsString(str_repeat('a', 96), $html);
    }

    private function makeInvoice(): Invoice
    {
        $tax = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)->setBase(100000.0)->setAmount(19000.0);
        return (new Invoice())
            ->setPrefijo('SETT')->setNumero('990000001')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setCompany((new Company())->setNit('900123456')->setRazonSocial('Royalty Fusion S.A.S')->setTipoDocumento('31'))
            ->setClient((new Client())->setTipoDocumento('13')->setNumeroDocumento('1010101010')->setRazonSocial('Juan Pérez')->setEmail('juan@example.test'))
            ->setTotal(119000.0)
            ->addItem((new Item())->setDescripcion('Servicio TI')->setCantidad(1.0)->setPrecio(100000.0)->addTax($tax))
            ->addTax($tax);
    }
}
