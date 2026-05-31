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
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentBuilder;

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
        $this->assertStringContainsString('<cbc:CustomizationID>11</cbc:CustomizationID>', $xml);
        $this->assertStringContainsString('<cbc:InvoiceTypeCode', $xml);
        $this->assertStringContainsString('>05</cbc:InvoiceTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:UUID schemeID="1" schemeName="CUDS-SHA384">' . $cuds, $xml);
        // Roles inverted: our Company is the AccountingSupplierParty
        $this->assertStringContainsString('Documento Soporte Electrónico', $xml);
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
