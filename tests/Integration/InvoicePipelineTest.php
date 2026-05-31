<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Catalog\TipoAmbiente;
use RoyaltyFusion\DianPhp\Model\AdditionalDocumentReference;
use RoyaltyFusion\DianPhp\Model\Address;
use RoyaltyFusion\DianPhp\Model\AllowanceCharge;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Contact;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Prepayment;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Report\HtmlReport;
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;
use RoyaltyFusion\DianPhp\Xml\QrGenerator;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

/**
 * End-to-end pipeline test (without actually signing or sending).
 *
 * Exercises every Phase 0-12 piece in one shot:
 *   build invoice -> calculator -> XmlBuilder -> validate -> render PDF
 *
 * No certificate is needed because the XAdES step is exercised in unit tests
 * by mocking the key material. This integration test is what consumers should
 * mirror in their own CI before going to Habilitación.
 */
final class InvoicePipelineTest extends TestCase
{
    public function testFullPipelineProducesValidXmlAndHtml(): void
    {
        $invoice = $this->buildRichInvoice();

        // 1. Validate
        $validation = (new BusinessRuleValidator())->validate($invoice);
        $this->assertTrue($validation->isValid(), implode("\n", $validation->messages()));

        // 2. Generate UUID + QR
        $cufeGen = new CufeGenerator();
        $uuid    = $cufeGen->generate($invoice, 'tech-key-test', CufeGenerator::ENV_HABILITACION);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $uuid);

        $qrUrl = (new QrGenerator())->generate($invoice, $uuid, false);
        $this->assertStringContainsString('documentkey=' . $uuid, $qrUrl);

        // 3. Build XML
        $xml = (new XmlBuilder(null, TipoAmbiente::HABILITACION->value))->build($invoice, $uuid, $qrUrl);
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('Invoice', $dom->documentElement->localName);

        // 4. Spot-check that every enriched section made it into the XML
        $this->assertStringContainsString('<cac:AllowanceCharge>', $xml);
        $this->assertStringContainsString('<cac:PrepaidPayment>', $xml);
        $this->assertStringContainsString('<cbc:AllowanceTotalAmount currencyID="COP">', $xml);
        $this->assertStringContainsString('<cbc:PrepaidAmount currencyID="COP">', $xml);
        $this->assertStringContainsString('<cac:OrderReference>', $xml);
        $this->assertStringContainsString('<cac:WithholdingTaxTotal>', $xml);
        $this->assertStringContainsString('<cac:PhysicalLocation>', $xml);
        $this->assertStringContainsString('<cbc:CountrySubentityCode>11</cbc:CountrySubentityCode>', $xml);
        $this->assertStringContainsString('<cbc:UUID schemeID="1" schemeName="CUFE-SHA384">' . $uuid, $xml);

        // 5. Render report HTML
        $html = (new HtmlReport())->render($invoice, $uuid, $qrUrl);
        $this->assertStringContainsString('Factura Electrónica de Venta', $html);
        $this->assertStringContainsString('Royalty Fusion S.A.S', $html);
        $this->assertStringContainsString($uuid, $html);
    }

    private function buildRichInvoice(): Invoice
    {
        $address = (new Address())
            ->setLine('Cra. 7 # 71-21')
            ->setCityCode('11001')->setCityName('Bogotá D.C.')
            ->setDepartmentCode('11')->setDepartmentName('Bogotá')
            ->setCountryCode('CO')->setCountryName('Colombia');

        $company = (new Company())
            ->setNit('900123456')
            ->setRazonSocial('Royalty Fusion S.A.S')
            ->setCommercialName('Royalty Fusion')
            ->setTipoDocumento('31')
            ->setRegimen('48')
            ->setResponsabilidades('O-13;O-15')
            ->setTipoOrganizacion('1')
            ->setIndustryClassificationCode('6201')
            ->setMunicipalityCode('11001')
            ->setAddress($address)
            ->setContact(
                (new Contact())
                    ->setName('Demo')
                    ->setTelephone('+57 300 0000000')
                    ->setElectronicMail('demo@example.test')
            );

        $client = (new Client())
            ->setTipoDocumento('13')
            ->setNumeroDocumento('1010101010')
            ->setRazonSocial('Cliente Demo')
            ->setEmail('cliente@example.test')
            ->setRegimen('49')
            ->setResponsabilidades('R-99-PN')
            ->setTipoOrganizacion('2');

        $software = (new Software())
            ->setId('11111111-2222-3333-4444-555555555555')
            ->setPin('00000')
            ->setProviderNit('900123456');

        $resolution = (new Resolution())
            ->setNumber('18760000001')
            ->setPrefix('SETT')
            ->setFrom('990000000')->setTo('995000000')
            ->setDateFrom(new \DateTimeImmutable('2026-01-01'))
            ->setDateTo(new \DateTimeImmutable('2026-12-31'));

        $iva = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)
            ->setBase(100000.0)->setAmount(19000.0);

        $reteFuente = (new Tax())->setCode('06')->setName('ReteFuente')->setPercent(2.5)
            ->setBase(100000.0)->setAmount(2500.0)->setIsRetention(true);

        $item = (new Item())
            ->setDescripcion('Consultoría TI')
            ->setCantidad(1.0)
            ->setPrecio(100000.0)
            ->setUnitCode('HUR')
            ->setCode('CONS-001')
            ->addTax($iva)
            ->addAllowanceCharge(
                (new AllowanceCharge())
                    ->setId(1)
                    ->setChargeIndicator(false)
                    ->setReasonCode('00')
                    ->setReason('Descuento fidelidad')
                    ->setAmount(5000.0)
                    ->setBaseAmount(100000.0)
            );

        return (new Invoice())
            ->setPrefijo('SETT')->setNumero('990000123')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setCompany($company)
            ->setClient($client)
            ->setSoftware($software)
            ->setResolution($resolution)
            ->setTechnicalKey('tech-key-test')
            // lineExt = 100000 - 5000 (desc. línea) = 95000
            // taxExcl = 95000 - 500 (desc. cab.) + 1500 (cargo cab.) = 96000
            // taxIncl = 96000 + 19000 (IVA, no rete) = 115000
            // payable = 115000 - 20000 (anticipo) = 95000
            ->setTotal(95000.0)
            ->addItem($item)
            ->addTax($iva)
            ->addTax($reteFuente)
            ->addPayment(
                (new Payment())
                    ->setMethodId('1')
                    ->setMeansId('10')
            )
            ->addPrepayment(
                (new Prepayment())
                    ->setId('ANT-001')
                    ->setPaidAmount(20000.0)
                    ->setPaidDate(new \DateTimeImmutable('2026-05-15'))
            )
            ->addAllowanceCharge(
                (new AllowanceCharge())
                    ->setId(1)
                    ->setChargeIndicator(true)
                    ->setReasonCode('01')
                    ->setReason('Cargo logístico')
                    ->setAmount(1500.0)
                    ->setBaseAmount(100000.0)
            )
            ->addAllowanceCharge(
                (new AllowanceCharge())
                    ->setId(2)
                    ->setChargeIndicator(false)
                    ->setReasonCode('00')
                    ->setReason('Descuento promocional cabecera')
                    ->setAmount(500.0)
                    ->setBaseAmount(100000.0)
            )
            ->addAdditionalDocumentReference(
                (new AdditionalDocumentReference())
                    ->setType('OrderReference')
                    ->setId('OC-12345')
                    ->setIssueDate(new \DateTimeImmutable('2026-05-15'))
            )
            ->addNote('Pago a 30 días.');
    }
}
