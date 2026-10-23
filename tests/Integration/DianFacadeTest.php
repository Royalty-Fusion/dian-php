<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Signer\SignatureVerifier;
use RoyaltyFusion\DianPhp\Tests\Support\DocumentFactory;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
use RoyaltyFusion\DianPhp\Validator\XmlConsistencyChecker;
use RoyaltyFusion\DianPhp\Ws\SoapClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * End-to-end through the facade with a mocked DIAN endpoint:
 * preflight → CUFE → XML → firma → WS-Security → respuesta.
 */
final class DianFacadeTest extends TestCase
{
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

    public function testPreflightOfAValidInvoiceIsReady(): void
    {
        $report = $this->dian()->preflight(DocumentFactory::invoice()->setTestSetId('set'));

        // The test certificate does not carry the NIT and expires soon: warnings only.
        $this->assertTrue($report->isReady(), $report->toText());
        $this->assertSame([], (new SignatureVerifier())->verify($report->getSignedXml()));
        $this->assertSame(
            [],
            (new XmlConsistencyChecker())->check($report->getSignedXml(), 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c', '12345', '2')->messages()
        );
    }

    public function testPreflightDetectsWrongTechnicalKeyFormatAndBlocksTheSend(): void
    {
        $invoice = DocumentFactory::invoice()->setTechnicalKey('no-es-una-clave');
        $dian    = $this->dian();

        $this->assertFalse($dian->preflight($invoice)->isReady());

        $result = $dian->send($invoice);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('SOFT_003', $result->getErrorMessage());
        $this->assertSame([], $this->bodies, 'Nothing must be sent when the pre-flight fails.');
    }

    public function testTestSetSendReturnsZipKeyAndStatusCanBePolled(): void
    {
        $upload = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><SendTestSetAsyncResponse xmlns="http://wcf.dian.colombia"><SendTestSetAsyncResult xmlns:b="x"><b:ErrorMessageList/><b:ZipKey>zip-123</b:ZipKey></SendTestSetAsyncResult></SendTestSetAsyncResponse></s:Body></s:Envelope>';
        $status = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><GetStatusZipResponse xmlns="http://wcf.dian.colombia"><GetStatusZipResult xmlns:b="x"><b:DianResponse><b:IsValid>true</b:IsValid><b:StatusCode>00</b:StatusCode><b:StatusDescription>Procesado Correctamente.</b:StatusDescription></b:DianResponse></GetStatusZipResult></GetStatusZipResponse></s:Body></s:Envelope>';

        $dian   = $this->dian($upload, $status);
        $result = $dian->send(DocumentFactory::invoice()->setTestSetId('test-set-uuid'));

        $this->assertTrue($result->isPending(), $result->getErrorMessage());
        $this->assertSame('zip-123', $result->getZipKey());
        $this->assertSame(96, strlen($result->getCufe()));
        $this->assertStringContainsString('SendTestSetAsync', $this->bodies[0]);
        $this->assertStringContainsString('<wcf:testSetId>test-set-uuid</wcf:testSetId>', $this->bodies[0]);
        $this->assertStringContainsString('<wcf:fileName>fv0900123456000263b023381.zip</wcf:fileName>', $this->bodies[0]);

        $final = $dian->waitForResult($result->getZipKey(), 1, 0);
        $this->assertTrue($final->isValid());
        $this->assertStringContainsString('<wcf:trackId>zip-123</wcf:trackId>', $this->bodies[1]);
    }

    public function testCreditNoteGoesThroughSendBillSyncWithoutTestSet(): void
    {
        $ok   = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><SendBillSyncResponse xmlns="http://wcf.dian.colombia"><SendBillSyncResult xmlns:b="x"><b:IsValid>true</b:IsValid><b:StatusCode>00</b:StatusCode><b:StatusDescription>Procesado Correctamente.</b:StatusDescription></SendBillSyncResult></SendBillSyncResponse></s:Body></s:Envelope>';
        $dian = $this->dian($ok)->validateBeforeSend(false);

        $result = $dian->send(DocumentFactory::creditNote());

        $this->assertTrue($result->isSuccess(), $result->getErrorMessage());
        $this->assertStringContainsString('SendBillSync', $this->bodies[0]);
        $this->assertStringStartsWith('nc', $result->getFileName());
    }

    public function testAttachedDocumentIsBuiltAndSigned(): void
    {
        $ok   = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><SendBillSyncResponse xmlns="http://wcf.dian.colombia"><SendBillSyncResult xmlns:b="x"><b:IsValid>true</b:IsValid><b:StatusCode>00</b:StatusCode><b:XmlBase64Bytes>PEFwcGxpY2F0aW9uUmVzcG9uc2UvPg==</b:XmlBase64Bytes></SendBillSyncResult></SendBillSyncResponse></s:Body></s:Envelope>';
        $dian = $this->dian($ok)->validateBeforeSend(false);
        $invoice = DocumentFactory::invoice();

        $result   = $dian->send($invoice);
        $attached = $dian->buildAttachedDocument($invoice, $result);

        $this->assertSame([], (new SignatureVerifier())->verify($attached));
        $this->assertStringContainsString('<cbc:ParentDocumentID>SETP990000001</cbc:ParentDocumentID>', $attached);
        $this->assertStringContainsString('<![CDATA[<ApplicationResponse/>]]>', $attached);
    }

    public function testInvalidEnvironmentIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dian('x', 'y', '2');
    }
}
