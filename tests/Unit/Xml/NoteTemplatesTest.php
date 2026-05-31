<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

final class NoteTemplatesTest extends TestCase
{
    public function testCreditNoteWithBillingReferenceRendersCustomization20(): void
    {
        $builder = new XmlBuilder();
        $xml     = $builder->build($this->buildCreditNote(true), str_repeat('b', 96), 'https://example.test/qr');

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('CreditNote', $dom->documentElement->localName);

        $this->assertStringContainsString('<cbc:CustomizationID>20</cbc:CustomizationID>', $xml);
        $this->assertStringContainsString('<cbc:UUID schemeID="1" schemeName="CUDE-SHA384">', $xml);
        $this->assertStringContainsString('<cac:BillingReference>', $xml);
        $this->assertStringContainsString('<cac:DiscrepancyResponse>', $xml);
        $this->assertStringContainsString('<cac:LegalMonetaryTotal>', $xml);
        $this->assertStringContainsString('<cac:CreditNoteLine>', $xml);
    }

    public function testCreditNoteWithoutBillingReferenceUsesCustomization22(): void
    {
        $builder = new XmlBuilder();
        $xml     = $builder->build($this->buildCreditNote(false), str_repeat('c', 96), 'https://example.test/qr');

        $this->assertStringContainsString('<cbc:CustomizationID>22</cbc:CustomizationID>', $xml);
    }

    public function testDebitNoteRendersRequestedMonetaryTotal(): void
    {
        $builder = new XmlBuilder();
        $xml     = $builder->build($this->buildDebitNote(), str_repeat('d', 96), 'https://example.test/qr');

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('DebitNote', $dom->documentElement->localName);

        $this->assertStringContainsString('<cbc:CustomizationID>30</cbc:CustomizationID>', $xml);
        $this->assertStringContainsString('<cac:RequestedMonetaryTotal>', $xml);
        $this->assertStringContainsString('<cac:DebitNoteLine>', $xml);
        $this->assertStringContainsString('<cbc:DebitedQuantity unitCode="94">', $xml);
    }

    private function buildCreditNote(bool $withBillingRef): CreditNote
    {
        $cn = (new CreditNote())
            ->setPrefijo('NC')
            ->setNumero('990000001')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setCompany((new Company())->setNit('901234567')->setRazonSocial('RF S.A.S')->setTipoDocumento('31'))
            ->setClient((new Client())->setTipoDocumento('13')->setNumeroDocumento('1010101010')->setRazonSocial('Cliente'))
            ->setSoftware((new Software())->setId('uuid-1')->setPin('00000')->setProviderNit('901234567'))
            ->setDiscrepancyResponse(
                (new DiscrepancyResponse())
                    ->setReferenceId('SETT990000001')
                    ->setResponseCode('2')
                    ->setDescription('Anulación')
            )
            ->setTotal(11900.0);

        if ($withBillingRef) {
            $cn->setBillingReference(
                (new BillingReference())
                    ->setNumber('SETT990000001')
                    ->setUuid(str_repeat('a', 96))
                    ->setDate(new \DateTimeImmutable('2026-05-28'))
            );
        }

        $tax = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)->setBase(10000.0)->setAmount(1900.0);
        $cn->addItem((new Item())->setDescripcion('Producto')->setCantidad(1.0)->setPrecio(10000.0)->addTax($tax));
        $cn->addTax($tax);

        return $cn;
    }

    private function buildDebitNote(): DebitNote
    {
        $dn = (new DebitNote())
            ->setPrefijo('ND')
            ->setNumero('990000001')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setCompany((new Company())->setNit('901234567')->setRazonSocial('RF S.A.S')->setTipoDocumento('31'))
            ->setClient((new Client())->setTipoDocumento('13')->setNumeroDocumento('1010101010')->setRazonSocial('Cliente'))
            ->setSoftware((new Software())->setId('uuid-1')->setPin('00000')->setProviderNit('901234567'))
            ->setBillingReference(
                (new BillingReference())
                    ->setNumber('SETT990000001')
                    ->setUuid(str_repeat('a', 96))
                    ->setDate(new \DateTimeImmutable('2026-05-28'))
            )
            ->setTotal(2380.0);

        $tax = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)->setBase(2000.0)->setAmount(380.0);
        $dn->addItem((new Item())->setDescripcion('Recargo')->setCantidad(1.0)->setPrecio(2000.0)->addTax($tax));
        $dn->addTax($tax);

        return $dn;
    }
}
