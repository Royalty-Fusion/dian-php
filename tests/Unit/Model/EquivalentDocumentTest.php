<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Catalog\TipoDocumentoEquivalente;
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\EquivalentDocument;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Tests\Support\DocumentFactory;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;

final class EquivalentDocumentTest extends TestCase
{
    public static function pos(): EquivalentDocument
    {
        $pos = new EquivalentDocument(TipoDocumentoEquivalente::POS);
        $pos->setPrefijo('POS')->setNumero('1001')
            ->setFecha(new \DateTimeImmutable('-1 hour', new \DateTimeZone('America/Bogota')))
            ->setCompany(DocumentFactory::company())
            ->setClient(Client::consumidorFinal())
            ->setSoftware(DocumentFactory::software())
            ->setResolution(
                (new Resolution())->setNumber('18764000000001')->setPrefix('POS')->setFrom('1')->setTo('100000')
                    ->setDateFrom(new \DateTimeImmutable('2025-01-01'))->setDateTo(new \DateTimeImmutable('2030-01-01'))
            )
            ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
            ->addItem((new Item())->setDescripcion('Café & pan')->setCantidad(2)->setPrecio(5000)
                ->addTax((new Tax())->setCode('01')->setPercent(19)->setBase(10000)));
        $pos->setFabricanteSoftware('Daniel Muñoz', 'Royalty Fusion S.A.S.', 'RF POS')
            ->setBeneficiosComprador('0', 'Consumidor final', 0)
            ->setPuntoVenta('CAJA-01', 'Bogotá - Local 1', 'Ana Pérez', 'Mostrador', 'V-1001');

        return $pos;
    }

    public function testPosIsAValidDocumentWithCude(): void
    {
        $dian   = new Dian(TestCertificate::path(), TestCertificate::PASSWORD);
        $pos    = self::pos();
        $report = $dian->preflight($pos);

        $this->assertTrue($report->isReady(), $report->toText());

        $xml = $report->getSignedXml();
        $this->assertStringContainsString('<cbc:InvoiceTypeCode>20</cbc:InvoiceTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:ProfileID>DIAN 2.1: Documento Equivalente POS</cbc:ProfileID>', $xml);
        $this->assertStringContainsString('schemeName="CUDE-SHA384"', $xml);
        $this->assertStringContainsString('<Name>PlacaCaja</Name><Value>CAJA-01</Value>', $xml);
        $this->assertStringContainsString('<Name>SubTotal</Name><Value>10000.00</Value>', $xml);
        $this->assertStringContainsString('<FabricanteSoftware><InformacionDelFabricanteDelSoftware>', $xml);
        $this->assertStringContainsString('>222222222222</cbc:CompanyID>', $xml);
        $this->assertStringContainsString('CUDE:', $xml);
        $this->assertStringContainsString('<sts:InvoiceControl>', $xml);

        // CUDE = same formula as the notes, with the software PIN.
        $this->assertSame((new CufeGenerator())->generate($pos, '12345'), $report->getUuid());

        // Five UBLExtensions: DIAN, fabricante, beneficios, caja, firma (last).
        $this->assertSame(5, substr_count($xml, '<ext:UBLExtension>'));
        $this->assertMatchesRegularExpression('#<PuntoVenta>.*</PuntoVenta>.*<ds:Signature#s', $xml);
    }

    public function testPosRequiresSoftwareManufacturerAndCashRegister(): void
    {
        $pos = new EquivalentDocument();
        $codes = array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($pos)->getErrors());

        $this->assertContains('DEE_001', $codes);
        $this->assertContains('DEE_002', $codes);
        $this->assertNotContains('SOFT_003', $codes, 'Documentos equivalentes use the PIN, not a clave técnica.');
    }

    public function testCreditAdjustmentNoteToEquivalentDocument(): void
    {
        $note = DocumentFactory::creditNote()
            ->setAjusteDocumentoEquivalente()
            ->setClient(Client::consumidorFinal())
            ->setBillingReference((new BillingReference())->setNumber('POS1001')->setUuid(str_repeat('b', 96))
                ->setDate(new \DateTimeImmutable('-1 day'))->setUuidSchemeName('CUDE-SHA384'))
            ->setDiscrepancyResponse((new DiscrepancyResponse())->setResponseCode('1')->setDescription('Devolución'));

        $dian   = new Dian(TestCertificate::path(), TestCertificate::PASSWORD);
        $report = $dian->preflight($note->setFecha(new \DateTimeImmutable('-1 hour')));
        $this->assertTrue($report->isReady(), $report->toText());

        $xml = $report->getSignedXml();
        $this->assertStringContainsString('<cbc:CreditNoteTypeCode>94</cbc:CreditNoteTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:ProfileID>DIAN 2.1: Nota de ajuste crédito al documento equivalente</cbc:ProfileID>', $xml);
        $this->assertStringContainsString('<cbc:UUID schemeName="CUDE-SHA384">' . str_repeat('b', 96), $xml);
    }

    public function testWrongReferenceSchemeIsDetected(): void
    {
        $note  = DocumentFactory::creditNote()->setAjusteDocumentoEquivalente();
        $codes = array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($note)->getErrors());

        $this->assertContains('NOTE_006', $codes);
        $this->assertInstanceOf(CreditNote::class, $note);
    }
}
