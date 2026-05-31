<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

/**
 * Smoke test that the Twig pipeline renders well-formed XML for an Invoice.
 * Schema/Anexo conformance tests live in tests/Integration once the validator
 * and the official XSDs are added (Phase 9).
 */
final class XmlBuilderTest extends TestCase
{
    public function testInvoiceRendersWellFormedXml(): void
    {
        $builder = new XmlBuilder();
        $xml     = $builder->build($this->makeInvoice(), str_repeat('a', 96), 'https://example.test/qr');

        $this->assertNotEmpty($xml);

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml);
        $this->assertTrue($loaded, 'XmlBuilder produced malformed XML.');

        $this->assertSame('Invoice', $dom->documentElement->localName);
        $this->assertStringContainsString('<cbc:UUID schemeID="1" schemeName="CUFE-SHA384">', $xml);
        $this->assertStringContainsString('https://example.test/qr', $xml);
        // Anexo V1.9 required fields
        $this->assertStringContainsString('<cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>', $xml);
        $this->assertStringContainsString('<cbc:ProfileExecutionID>2</cbc:ProfileExecutionID>', $xml);
        $this->assertStringContainsString('listAgencyID="195"', $xml);
        $this->assertStringContainsString('<cac:LegalMonetaryTotal>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="COP">', $xml);
    }

    private function makeInvoice(): Invoice
    {
        $company = (new Company())
            ->setNit('901234567')
            ->setRazonSocial('Royalty Fusion S.A.S')
            ->setTipoDocumento('31')
            ->setRegimen('48')
            ->setResponsabilidades('O-47;R-99-PN')
            ->setTipoOrganizacion('1');

        $client = (new Client())
            ->setTipoDocumento('13')
            ->setNumeroDocumento('1010101010')
            ->setRazonSocial('Juan Pérez')
            ->setEmail('juan.perez@example.com');

        $software = (new Software())
            ->setId('d35e1234-abcd-1234-abcd-0123456789ab')
            ->setPin('12345')
            ->setProviderNit('901234567');

        $resolution = (new Resolution())
            ->setNumber('18760000001')
            ->setPrefix('SETT')
            ->setFrom('990000000')
            ->setTo('995000000')
            ->setDateFrom(new \DateTimeImmutable('2026-01-01'))
            ->setDateTo(new \DateTimeImmutable('2026-12-31'));

        $tax = (new Tax())
            ->setCode('01')->setName('IVA')->setPercent(19.0)
            ->setBase(100000.0)->setAmount(19000.0);

        $item = (new Item())
            ->setDescripcion('Item Demo')->setCantidad(1.0)->setPrecio(100000.0)
            ->addTax($tax);

        return (new Invoice())
            ->setPrefijo('SETT')
            ->setNumero('990000001')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setCompany($company)
            ->setClient($client)
            ->setSoftware($software)
            ->setResolution($resolution)
            ->setTechnicalKey('fc8eac422eba16e22ffd8c6f94b3f40a6e38162c')
            ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d')
            ->setTotal(119000.0)
            ->addItem($item)
            ->addTax($tax);
    }
}
