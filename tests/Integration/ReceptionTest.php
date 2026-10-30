<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseBuilder;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseEvent;
use RoyaltyFusion\DianPhp\Radian\EventCudeGenerator;
use RoyaltyFusion\DianPhp\Signer\SignatureVerifier;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
use RoyaltyFusion\DianPhp\Validator\XsdValidator;
use RoyaltyFusion\DianPhp\Ws\SoapClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Recepción de facturas de proveedores (real DIAN-approved AttachedDocuments)
 * and the RADIAN events that answer them.
 */
final class ReceptionTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../resources/fixtures';

    /** @var list<string> */
    private array $bodies = [];

    private function dian(string ...$responses): Dian
    {
        $mocks = array_map(fn (string $body) => function (string $m, string $u, array $o) use ($body): MockResponse {
            $this->bodies[] = (string) $o['body'];
            return new MockResponse($body);
        }, $responses);

        return (new Dian(TestCertificate::path(), TestCertificate::PASSWORD, SoapClient::ENV_HABILITACION))
            ->setSoapClient(new SoapClient(SoapClient::ENV_HABILITACION, new MockHttpClient($mocks), null, null, 0));
    }

    private static function buyer(): Company
    {
        return (new Company())->setNit('901956924')->setRazonSocial('Comprador S.A.S.')
            ->setTipoDocumento('31')->setResponsabilidades('O-13');
    }

    private static function software(): Software
    {
        return (new Software())->setId('sw-id')->setPin('12345')->setProviderNit('901956924');
    }

    public function testReadsARealAttachedDocumentWithRetentions(): void
    {
        $doc = $this->dian()->receive((string) file_get_contents(self::FIXTURES . '/siigo-blindacces-FV7.xml'));

        $this->assertSame('Invoice', $doc->type);
        $this->assertSame('01', $doc->typeCode);
        $this->assertSame('FV7', $doc->number);
        $this->assertSame(96, strlen($doc->uuid));
        $this->assertSame('CUFE-SHA384', $doc->uuidSchemeName);
        $this->assertSame('901944237', $doc->supplier['nit']);
        $this->assertSame('8', $doc->supplier['dv']);
        $this->assertSame('BLINDACCES SAS', $doc->supplier['name']);
        $this->assertSame(20384700.0, $doc->totals['payable']);
        $this->assertSame(['01' => 3254700.0], $doc->taxes);
        $this->assertSame(['06' => 428250.0, '07' => 189115.2], $doc->withholdings);
        $this->assertCount(1, $doc->lines);
        $this->assertTrue($doc->dianApproved);
        $this->assertSame([], $doc->signatureProblems);
        $this->assertTrue($doc->isTrustworthy());
        $this->assertTrue($doc->isAddressedTo('901.956.924'));
        $this->assertFalse($doc->isAddressedTo('900000000'));
    }

    public function testCreditNoteKeepsTheCorrectedInvoice(): void
    {
        $doc = $this->dian()->receive((string) file_get_contents(self::FIXTURES . '/siigo-blindacces-NC3.xml'));

        $this->assertSame('CreditNote', $doc->type);
        $this->assertSame('91', $doc->typeCode);
        $this->assertCount(1, $doc->billingReferences);
        $this->assertSame(96, strlen($doc->billingReferences[0]['uuid']));
    }

    public function testTamperedDocumentIsNotTrustworthy(): void
    {
        $xml = (string) file_get_contents(self::FIXTURES . '/siigo-blindacces-FV3.xml');
        $doc = $this->dian()->receive($xml);
        $tampered = str_replace($doc->signedXml, str_replace('BLINDACCES SAS', 'OTRA SAS', $doc->signedXml), $xml);

        $this->assertNotSame([], $this->dian()->receive($tampered)->signatureProblems);
        $this->assertFalse($this->dian()->receive($doc->signedXml)->isTrustworthy(), 'Without the DIAN ApplicationResponse it is not approved.');
    }

    public function testRejectsUnsupportedXml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->dian()->receive('<Foo/>');
    }

    public function testEventsAnsweringAReceivedInvoiceAreValid(): void
    {
        $doc = $this->dian()->receive((string) file_get_contents(self::FIXTURES . '/siigo-blindacces-FV7.xml'));

        foreach ([
            ApplicationResponseEvent::acuseRecibo(),
            ApplicationResponseEvent::reciboBienServicio(),
            ApplicationResponseEvent::aceptacionExpresa(),
            ApplicationResponseEvent::reclamo('02'),
        ] as $i => $event) {
            $eventDoc = $doc->toEvent($event, 'EV-' . ($i + 1), self::buyer(), self::software())
                ->setPerson('1010101010', 'Ana', 'Pérez', 'Almacenista', 'Bodega');
            $eventDoc->setCude((new EventCudeGenerator())->generate($eventDoc, '12345'));
            $signed = (new \RoyaltyFusion\DianPhp\Signer\XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))
                ->sign((new ApplicationResponseBuilder())->build($eventDoc));

            $this->assertSame([], (new XsdValidator())->validate($signed)->messages());
            $this->assertSame([], (new SignatureVerifier())->verify($signed));
            $this->assertStringContainsString('<cbc:ID>FV7</cbc:ID>', $signed);
            $this->assertStringContainsString($doc->uuid, $signed);
            $this->assertStringContainsString('>901944237</cbc:CompanyID>', $signed);
        }
        $this->assertStringContainsString('<cbc:ResponseCode listID="02" name="Mercancía no entregada totalmente">031</cbc:ResponseCode>', $signed);
    }

    public function testAcknowledgeSendsTheEventOnlyForOwnDocuments(): void
    {
        $ok   = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><SendEventUpdateStatusResponse xmlns="http://wcf.dian.colombia"><SendEventUpdateStatusResult xmlns:b="x"><b:IsValid>true</b:IsValid><b:StatusCode>00</b:StatusCode><b:StatusDescription>Procesado Correctamente.</b:StatusDescription><b:XmlDocumentKey>abc</b:XmlDocumentKey></SendEventUpdateStatusResult></SendEventUpdateStatusResponse></s:Body></s:Envelope>';
        $dian = $this->dian($ok);
        $doc  = $dian->receive((string) file_get_contents(self::FIXTURES . '/siigo-blindacces-FV7.xml'));

        $other = (clone self::buyer())->setNit('900000000');
        $this->assertFalse($dian->acknowledge($doc, ApplicationResponseEvent::acuseRecibo(), 'EV-1', $other, self::software())->isSuccess());
        $this->assertFalse($dian->acknowledge($doc, ApplicationResponseEvent::reciboBienServicio(), 'EV-2', self::buyer(), self::software())->isSuccess());
        $this->assertSame([], $this->bodies);

        $result = $dian->acknowledge($doc, ApplicationResponseEvent::acuseRecibo(), 'EV-1', self::buyer(), self::software());
        $this->assertTrue($result->isSuccess(), $result->getErrorMessage());
        $this->assertStringContainsString('SendEventUpdateStatus', $this->bodies[0]);
    }

    public function testGetAcquirer(): void
    {
        $found = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><GetAcquirerResponse xmlns="http://wcf.dian.colombia"><GetAcquirerResult xmlns:b="x"><b:Message>Consulta exitosa</b:Message><b:ReceiverEmail>facturas@cliente.co</b:ReceiverEmail><b:ReceiverName>CLIENTE &amp; CIA S.A.S.</b:ReceiverName><b:StatusCode>200</b:StatusCode></GetAcquirerResult></GetAcquirerResponse></s:Body></s:Envelope>';
        $empty = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><GetAcquirerResponse xmlns="http://wcf.dian.colombia"><GetAcquirerResult xmlns:b="x"><b:Message>No encontrado</b:Message><b:StatusCode>404</b:StatusCode></GetAcquirerResult></GetAcquirerResponse></s:Body></s:Envelope>';
        $dian = $this->dian($found, $empty);

        $acquirer = $dian->getAcquirer('31', '900123456');
        $this->assertNotNull($acquirer);
        $this->assertSame('CLIENTE & CIA S.A.S.', $acquirer->name);
        $this->assertSame('facturas@cliente.co', $acquirer->email);
        $this->assertStringContainsString('<wcf:identificationNumber>900123456</wcf:identificationNumber>', $this->bodies[0]);

        $this->assertNull($dian->getAcquirer('13', '1'));
    }
}
