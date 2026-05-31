<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Radian;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseBuilder;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseEvent;
use RoyaltyFusion\DianPhp\Radian\EventDocument;

final class EventDocumentTest extends TestCase
{
    public function testBuilderProducesAcuseReciboApplicationResponse(): void
    {
        $event = (new ApplicationResponseEvent())
            ->setCode(ApplicationResponseEvent::ACUSE_RECIBO)
            ->setDescription('Acuse de recibo de la factura electrónica');

        $doc = (new EventDocument())
            ->setId('EVT-001')
            ->setCude(str_repeat('b', 96))
            ->setIssueDate(new \DateTimeImmutable('2026-05-30T10:00:00-05:00'))
            ->setEvent($event)
            ->setInvoiceCufe(str_repeat('a', 96))
            ->setInvoiceId('FV-12345')
            ->setInvoiceDate(new \DateTimeImmutable('2026-05-29'))
            ->setReceiver((new Company())
                ->setNit('901234567')->setRazonSocial('Adquiriente S.A.S')
                ->setTipoDocumento('31')->setResponsabilidades('O-13'))
            ->setSupplier((new Client())
                ->setNumeroDocumento('900123456')->setRazonSocial('Proveedor S.A.S')
                ->setTipoDocumento('31')->setResponsabilidades('R-99-PN'));

        $xml = (new ApplicationResponseBuilder())->build($doc);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('ApplicationResponse', $dom->documentElement->localName);

        $this->assertStringContainsString('<cbc:CustomizationID>030</cbc:CustomizationID>', $xml);
        $this->assertStringContainsString('<cbc:ProfileID>DIAN 2.1: Aplicación de Respuesta</cbc:ProfileID>', $xml);
        $this->assertStringContainsString('<cbc:ResponseCode>030</cbc:ResponseCode>', $xml);
        $this->assertStringContainsString('FV-12345', $xml);
        $this->assertStringContainsString(str_repeat('a', 96), $xml);
        $this->assertStringContainsString('Adquiriente S.A.S', $xml);
    }

    public function testEventDocumentExposesAllEventCodes(): void
    {
        $codes = [
            ApplicationResponseEvent::ACUSE_RECIBO          => '030',
            ApplicationResponseEvent::RECLAMO               => '031',
            ApplicationResponseEvent::RECIBO_BIEN_SERVICIO  => '032',
            ApplicationResponseEvent::ACEPTACION_EXPRESA    => '033',
            ApplicationResponseEvent::ACEPTACION_TACITA     => '034',
            ApplicationResponseEvent::MANDATO               => '035',
            ApplicationResponseEvent::ENDOSO_PROPIEDAD      => '036',
            ApplicationResponseEvent::PAGO                  => '042',
        ];
        foreach ($codes as $const => $expected) {
            $this->assertSame($expected, $const);
        }
    }
}
