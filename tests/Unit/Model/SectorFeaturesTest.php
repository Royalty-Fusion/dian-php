<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Catalog\TipoFactura;
use RoyaltyFusion\DianPhp\Catalog\TipoOperacion;
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\AdditionalDocumentReference;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\Delivery;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Sector\SectorSalud;
use RoyaltyFusion\DianPhp\Tests\Support\DocumentFactory;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;
use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;

/**
 * Referencias, impuestos por unidad, mandatos, exportación (Incoterm) y
 * sector salud: every variant must pass the full preflight (reglas + XSD + firma).
 */
final class SectorFeaturesTest extends TestCase
{
    private static function recent(Invoice $invoice): Invoice
    {
        return $invoice->setFecha(new \DateTimeImmutable('-1 hour', new \DateTimeZone('America/Bogota')))
            ->setDueDate(new \DateTimeImmutable('+30 days'));
    }

    private static function preflightXml(Invoice|\RoyaltyFusion\DianPhp\Model\CreditNote $doc): string
    {
        $report = (new Dian(TestCertificate::path(), TestCertificate::PASSWORD))->preflight($doc);
        self::assertTrue($report->isReady(), $report->toText());

        return (string) $report->getSignedXml();
    }

    /** @return list<string> codes of every issue (errors and warnings) */
    private static function codes(Invoice $invoice): array
    {
        return array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($invoice)->getErrors());
    }

    public function testPerUnitTaxHasNoBaseNorPercent(): void
    {
        $invoice = self::recent(DocumentFactory::invoice());
        $invoice->addItem((new Item())->setDescripcion('Bolsa plástica')->setCantidad(3)->setPrecio(100)
            ->addTax((new Tax())->setCode('22')->setPerUnit(66, 3)));

        $totals = DocumentCalculator::totals($invoice);
        $this->assertSame(198.0, $totals['taxTotalsByCode']['22'] ?? null);
        // The per-unit tax does not add to the taxable base (TaxExclusiveAmount).
        $this->assertSame(95000.0 + 30001.0, $totals['taxExclusiveAmount']);

        $xml = self::preflightXml($invoice);
        $this->assertStringContainsString('<cbc:BaseUnitMeasure unitCode="94">3.000000</cbc:BaseUnitMeasure>', $xml);
        $this->assertStringContainsString('<cbc:PerUnitAmount currencyID="COP">66.00</cbc:PerUnitAmount>', $xml);
        $this->assertMatchesRegularExpression('#<cbc:ID>22</cbc:ID>\s*<cbc:Name>INC Bolsas</cbc:Name>#', $xml);
    }

    public function testReferencesAreRenderedInUblOrder(): void
    {
        $invoice = self::recent(DocumentFactory::invoice());
        foreach ([
            AdditionalDocumentReference::ADDITIONAL => 'OTRO-1',
            AdditionalDocumentReference::CONTRACT   => 'CT-9',
            AdditionalDocumentReference::RECEIPT    => 'REC-3',
            AdditionalDocumentReference::DESPATCH   => 'REM-5',
        ] as $type => $id) {
            $invoice->addAdditionalDocumentReference((new AdditionalDocumentReference())->setType($type)->setId($id)
                ->setIssueDate(new \DateTimeImmutable('2026-05-01')));
        }

        $xml = self::preflightXml($invoice);
        $this->assertMatchesRegularExpression(
            '#<cac:OrderReference>.*<cac:DespatchDocumentReference>\s*<cbc:ID>REM-5.*<cac:ReceiptDocumentReference>\s*<cbc:ID>REC-3'
            . '.*<cac:ContractDocumentReference>\s*<cbc:ID>CT-9.*<cac:AdditionalDocumentReference>\s*<cbc:ID>OTRO-1#s',
            $xml
        );
    }

    public function testCreditNoteCanReferenceSeveralInvoices(): void
    {
        $note = DocumentFactory::creditNote();
        $note->setFecha(new \DateTimeImmutable('-1 hour', new \DateTimeZone('America/Bogota')));
        $note->addBillingReference((new BillingReference())->setNumber('SETP990000002')->setUuid(str_repeat('b', 96))
            ->setDate(new \DateTimeImmutable('2026-05-29')));

        $xml = self::preflightXml($note);
        $this->assertSame(2, substr_count($xml, '<cac:BillingReference>'));
        $this->assertStringContainsString('<cbc:ID>SETP990000002</cbc:ID>', $xml);
        $this->assertSame('20', $note->getCustomizationId());
        $this->assertSame('SETP990000001', $note->getBillingReference()?->getNumber());

        $note->addBillingReference((new BillingReference())->setNumber('X')->setUuid('corto'));
        $codes = array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($note)->getErrors());
        $this->assertContains('NOTE_003', $codes);
    }

    public function testMandatosLineCarriesTheMandante(): void
    {
        $invoice = self::recent(DocumentFactory::invoice())->setTipoOperacion(TipoOperacion::MANDATOS);
        $this->assertContains('INV_007', self::codes($invoice));

        $invoice->getItems()[0]->setMandante('900123456')->addProperty('01', 'REM-77');
        $xml = self::preflightXml($invoice);
        $this->assertStringContainsString('<cbc:CustomizationID>11</cbc:CustomizationID>', $xml);
        $this->assertMatchesRegularExpression(
            '#<cac:AdditionalItemProperty>\s*<cbc:Name>01</cbc:Name>\s*<cbc:Value>REM-77</cbc:Value>\s*</cac:AdditionalItemProperty>\s*'
            . '<cac:InformationContentProviderParty>\s*<cac:PowerOfAttorney>\s*<cac:AgentParty>\s*<cac:PartyIdentification>\s*'
            . '<cbc:ID schemeAgencyID="195"[^>]*schemeID="8" schemeName="31">900123456</cbc:ID>#',
            $xml
        );
    }

    public function testExportInvoiceWithIncoterm(): void
    {
        $invoice = self::recent(DocumentFactory::invoice())->setTipoFactura(TipoFactura::EXPORTACION);
        $this->assertContains('INV_004', self::codes($invoice));

        $invoice->setDelivery((new Delivery())->setIncoterm('XYZ'));
        $this->assertContains('INV_005', self::codes($invoice));

        $invoice->setDelivery((new Delivery())
            ->setIncoterm('fob')
            ->setDate(new \DateTimeImmutable('2026-06-01'))
            ->setAddress(DocumentFactory::address('Puerto de Cartagena')));
        $this->assertNotContains('INV_005', self::codes($invoice));

        $xml = (new Dian(TestCertificate::path(), TestCertificate::PASSWORD))->preflight($invoice)->getSignedXml();
        $this->assertMatchesRegularExpression(
            '#<cac:Delivery>\s*<cbc:ActualDeliveryDate>2026-06-01</cbc:ActualDeliveryDate>\s*<cac:DeliveryAddress>.*</cac:Delivery>\s*'
            . '<cac:DeliveryTerms>\s*<cbc:LossRiskResponsibilityCode>FOB</cbc:LossRiskResponsibilityCode>\s*'
            . '<cbc:LossRisk>Franco a bordo</cbc:LossRisk>\s*</cac:DeliveryTerms>\s*<cac:PaymentMeans>#s',
            (string) $xml
        );
    }

    public function testHealthSectorInvoice(): void
    {
        $invoice = self::recent(DocumentFactory::invoice())->setTipoOperacion(SectorSalud::SS_CUFE);
        $this->assertContains('INV_006', self::codes($invoice));

        $invoice->addExtension((new SectorSalud('Resolución 2275:2023'))->addUsuario([
            'CODIGO_PRESTADOR' => '110010000001',
            'MODALIDAD_PAGO'   => ['04', 'Pago por evento', 'salud_modalidad_pago.gc'],
            'NUMERO_CONTRATO'  => 'CT & 01',
            'COPAGO'           => 0,
        ])->toExtension());

        $xml = self::preflightXml($invoice);
        $this->assertStringContainsString('<cbc:CustomizationID>SS-CUFE</cbc:CustomizationID>', $xml);
        $this->assertStringContainsString('<Value>Resolución 2275:2023</Value>', $xml);
        $this->assertStringContainsString('<Group schemeName="Sector Salud"><Collection schemeName="Usuario">', $xml);
        $this->assertStringContainsString('<Name>MODALIDAD_PAGO</Name><Value schemeName="salud_modalidad_pago.gc" schemeID="04">Pago por evento</Value>', $xml);
        $this->assertStringContainsString('<Value>CT &amp; 01</Value>', $xml);
        // Interoperabilidad goes first, the signature always last.
        $this->assertMatchesRegularExpression('#<ext:UBLExtension>\s*<ext:ExtensionContent><CustomTagGeneral>.*<sts:DianExtensions>.*<ds:Signature#s', $xml);
    }
}
