<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\SupportDocument;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\SupportDocument\CudsGenerator;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocument;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentAdjustmentNote;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentBuilder;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Signer\XadesSigner;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
use RoyaltyFusion\DianPhp\Validator\XsdValidator;

final class SupportDocumentTest extends TestCase
{
    public function testCudsIsDeterministicSha384(): void
    {
        $cuds = (new CudsGenerator())->generate($this->buildDs(), 'pin-test', CudsGenerator::ENV_HABILITACION);
        $this->assertSame(96, strlen($cuds));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $cuds);
    }

    public function testCudsChangesWithEnvironment(): void
    {
        $gen = new CudsGenerator();
        $hab  = $gen->generate($this->buildDs(), 'p', CudsGenerator::ENV_HABILITACION);
        $prod = $gen->generate($this->buildDs(), 'p', CudsGenerator::ENV_PRODUCCION);
        $this->assertNotSame($hab, $prod);
    }

    public function testBuilderProducesWellFormedXmlWithCustomization11(): void
    {
        $cuds = str_repeat('a', 96);
        $xml  = (new SupportDocumentBuilder())->build($this->buildDs(), $cuds, 'https://example.test/qr');

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('Invoice', $dom->documentElement->localName);
        $this->assertStringContainsString('<cbc:CustomizationID>10</cbc:CustomizationID>', $xml);
        $this->assertStringContainsString('<cbc:InvoiceTypeCode>05</cbc:InvoiceTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:UUID schemeID="2" schemeName="CUDS-SHA384">' . $cuds, $xml);
        $this->assertStringContainsString('<cbc:DescriptionCode>1</cbc:DescriptionCode>', $xml);

        // Seller (no obligado) is the supplier party, the issuing company the customer party.
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->assertSame('1010101010', $xpath->evaluate('string(//cac:AccountingSupplierParty//cac:PartyTaxScheme/cbc:CompanyID)'));
        $this->assertSame('901234567', $xpath->evaluate('string(//cac:AccountingCustomerParty//cac:PartyTaxScheme/cbc:CompanyID)'));

        $nonResident = (new SupportDocumentBuilder())->build($this->buildDs()->setCustomizationId(SupportDocument::NO_RESIDENTE), $cuds);
        $this->assertStringContainsString('<cbc:CustomizationID>11</cbc:CustomizationID>', $nonResident);
    }

    public function testCudsFollowsTheAnexoFormula(): void
    {
        $this->assertSame(
            'DS12026-05-3010:00:00-05:0050000.00019500.0059500.001010101010901234567123452',
            (new CudsGenerator())->buildString($this->buildDs(), '12345', CudsGenerator::ENV_HABILITACION)
        );
    }

    public function testSignedSupportDocumentAndAdjustmentNoteAreSchemaValid(): void
    {
        $signer = new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD);
        $xsd    = new XsdValidator();

        $ds   = $this->buildDs();
        $cuds = (new CudsGenerator())->generate($ds, '12345');
        $xml  = $signer->sign((new SupportDocumentBuilder())->build($ds, $cuds));
        $this->assertSame([], $xsd->validate($xml)->messages());

        $note = (new SupportDocumentAdjustmentNote())
            ->setPrefijo('NA')->setNumero('1')
            ->setFecha(new \DateTimeImmutable('2026-05-31T10:00:00-05:00'))
            ->setCompany($ds->getCompany() ?? new Company())
            ->setSupplier($ds->getSupplier() ?? new Client())
            ->setSoftware($ds->getSoftware() ?? new Software())
            ->addItem($ds->getItems()[0]);
        $note->setBillingReference((new BillingReference())->setNumber('DS1')->setUuid($cuds)->setDate(new \DateTimeImmutable('2026-05-30')));
        $note->setDiscrepancyResponse((new DiscrepancyResponse())->setResponseCode('2')->setDescription('Anulación'));

        $noteXml = $signer->sign((new SupportDocumentBuilder())->build($note, (new CudsGenerator())->generate($note, '12345')));
        $this->assertSame([], $xsd->validate($noteXml)->messages());
        $this->assertStringContainsString('<cbc:CreditNoteTypeCode>95</cbc:CreditNoteTypeCode>', $noteXml);
    }

    private function buildDs(): SupportDocument
    {
        $company = (new Company())
            ->setNit('901234567')
            ->setRazonSocial('Adquiriente S.A.S')
            ->setTipoDocumento('31')
            ->setRegimen('48')
            ->setResponsabilidades('O-13')
            ->setTipoOrganizacion('1');

        $supplier = (new Client())
            ->setTipoDocumento('13')
            ->setNumeroDocumento('1010101010')
            ->setRazonSocial('Proveedor No Obligado')
            ->setResponsabilidades('R-99-PN')
            ->setTipoOrganizacion('2');

        $software = (new Software())
            ->setId('uuid-ds')->setPin('12345')->setProviderNit('901234567');

        $iva  = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)->setBase(50000.0)->setAmount(9500.0);
        $item = (new Item())->setDescripcion('Servicio puntual')->setCantidad(1.0)->setPrecio(50000.0)->addTax($iva);

        return (new SupportDocument())
            ->setPrefijo('DS')->setNumero('1')
            ->setFecha(new \DateTimeImmutable('2026-05-30T10:00:00-05:00'))
            ->setCompany($company)
            ->setSupplier($supplier)
            ->setSoftware($software)
            ->setTotal(59500.0)
            ->addItem($item)
            ->addTax($iva);
    }
}
