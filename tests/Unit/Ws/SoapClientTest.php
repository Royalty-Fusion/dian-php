<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Ws;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Signer\Certificate;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
use RoyaltyFusion\DianPhp\Ws\FileNameGenerator;
use RoyaltyFusion\DianPhp\Ws\SoapClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SoapClientTest extends TestCase
{
    private const SYNC_OK = <<<'XML'
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><SendBillSyncResponse xmlns="http://wcf.dian.colombia"><SendBillSyncResult xmlns:b="http://schemas.datacontract.org/2004/07/DianResponse" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
<b:ErrorMessage xmlns:c="http://schemas.microsoft.com/2003/10/Serialization/Arrays"><c:string>Regla: FAJ44b, Notificación: Nit o Documento de Identificación informado No se encuentra registrado en el RUT.</c:string></b:ErrorMessage>
<b:IsValid>true</b:IsValid><b:StatusCode>00</b:StatusCode><b:StatusDescription>Procesado Correctamente.</b:StatusDescription>
<b:StatusMessage>La Factura electrónica SETP990000001, ha sido autorizada.</b:StatusMessage>
<b:XmlBase64Bytes>PEFwcGxpY2F0aW9uUmVzcG9uc2UvPg==</b:XmlBase64Bytes><b:XmlBytes i:nil="true"/>
<b:XmlDocumentKey>abc123</b:XmlDocumentKey><b:XmlFileName>fv09001234560002600000001</b:XmlFileName>
</SendBillSyncResult></SendBillSyncResponse></s:Body></s:Envelope>
XML;

    private const SYNC_REJECTED = <<<'XML'
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><SendBillSyncResponse xmlns="http://wcf.dian.colombia"><SendBillSyncResult xmlns:b="http://schemas.datacontract.org/2004/07/DianResponse">
<b:ErrorMessage xmlns:c="http://schemas.microsoft.com/2003/10/Serialization/Arrays"><c:string>Regla: FAD06, Rechazo: Valor del CUFE no está calculado correctamente.</c:string><c:string>Regla: FAJ44b, Notificación: NIT no registrado.</c:string></b:ErrorMessage>
<b:IsValid>false</b:IsValid><b:StatusCode>99</b:StatusCode><b:StatusDescription>Validación contiene errores en campos mandatorios.</b:StatusDescription>
</SendBillSyncResult></SendBillSyncResponse></s:Body></s:Envelope>
XML;

    private const TEST_SET_OK = <<<'XML'
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><SendTestSetAsyncResponse xmlns="http://wcf.dian.colombia"><SendTestSetAsyncResult xmlns:b="http://schemas.datacontract.org/2004/07/UploadDocumentResponse"><b:ErrorMessageList/><b:ZipKey>4a5b6c7d-0000-1111-2222-333344445555</b:ZipKey></SendTestSetAsyncResult></SendTestSetAsyncResponse></s:Body></s:Envelope>
XML;

    private const FAULT = <<<'XML'
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><s:Fault><s:Code><s:Value>s:Sender</s:Value><s:Subcode><s:Value xmlns:a="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">a:InvalidSecurity</s:Value></s:Subcode></s:Code><s:Reason><s:Text xml:lang="es-CO">An error occurred when verifying security for the message.</s:Text></s:Reason></s:Fault></s:Body></s:Envelope>
XML;

    private const RANGES = <<<'XML'
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><GetNumberingRangeResponse xmlns="http://wcf.dian.colombia"><GetNumberingRangeResult xmlns:b="http://schemas.datacontract.org/2004/07/NumberRangeResponseList"><b:OperationCode>100</b:OperationCode><b:OperationDescription>Acción completada OK.</b:OperationDescription><b:ResponseList xmlns:c="http://schemas.datacontract.org/2004/07/NumberRangeResponse"><c:NumberRangeResponse><c:ResolutionNumber>18760000001</c:ResolutionNumber><c:Prefix>SETP</c:Prefix><c:FromNumber>990000000</c:FromNumber><c:ToNumber>995000000</c:ToNumber><c:TechnicalKey>fc8eac422eba16e22ffd8c6f94b3f40a6e38162c</c:TechnicalKey></c:NumberRangeResponse></b:ResponseList></GetNumberingRangeResult></GetNumberingRangeResponse></s:Body></s:Envelope>
XML;

    /** @var list<array{method:string,url:string,options:array<string,mixed>}> */
    private array $requests = [];

    private function client(string ...$bodies): SoapClient
    {
        $responses = [];
        foreach ($bodies as $body) {
            $status = $body === 'HTTP503' ? 503 : 200;
            $responses[] = function (string $method, string $url, array $options) use ($body, $status): MockResponse {
                $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
                return new MockResponse($status === 503 ? 'Service Unavailable' : $body, ['http_code' => $status]);
            };
        }

        return new SoapClient(
            SoapClient::ENV_HABILITACION,
            new MockHttpClient($responses),
            Certificate::fromPkcs12File(TestCertificate::path(), TestCertificate::PASSWORD),
            null,
            1
        );
    }

    public function testEnvelopeCarriesWsSecurityAndAddressing(): void
    {
        $this->client(self::SYNC_OK)->sendBillSync('fv0900123456000260000001', '<Invoice/>', 'abc123');

        $body = (string) $this->requests[0]['options']['body'];
        $this->assertStringContainsString('<wsse:BinarySecurityToken', $body);
        $this->assertStringContainsString('<wsu:Timestamp', $body);
        $this->assertStringContainsString('<wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillSync</wsa:Action>', $body);
        $this->assertStringContainsString('<wsa:To', $body);
        $this->assertStringContainsString('<wcf:fileName>fv0900123456000260000001.zip</wcf:fileName>', $body);
        $this->assertSame(SoapClient::ENDPOINT_HAB, $this->requests[0]['url']);

        preg_match('#<wcf:contentFile>([^<]+)</wcf:contentFile>#', $body, $m);
        $this->assertStringStartsWith('PK', (string) base64_decode($m[1]));
    }

    public function testSuccessfulSyncResponse(): void
    {
        $result = $this->client(self::SYNC_OK)->sendBillSync('fv1', '<Invoice/>', 'abc123');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('00', $result->getStatusCode());
        $this->assertSame([], $result->getErrors());
        $this->assertCount(1, $result->getNotifications());
        $this->assertSame('<ApplicationResponse/>', $result->getApplicationResponseXml());
    }

    public function testRejectedSyncResponseSeparatesRejectionsFromNotifications(): void
    {
        $result = $this->client(self::SYNC_REJECTED)->sendBillSync('fv1', '<Invoice/>');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['Regla: FAD06, Rechazo: Valor del CUFE no está calculado correctamente.'], $result->getErrors());
        $this->assertStringContainsString('FAD06', $result->getErrorMessage());
        $this->assertCount(1, $result->getNotifications());
    }

    public function testTestSetReturnsZipKeyAsPending(): void
    {
        $result = $this->client(self::TEST_SET_OK)->send('fv1', '<Invoice/>', 'test-set-id');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isPending());
        $this->assertSame('4a5b6c7d-0000-1111-2222-333344445555', $result->getZipKey());
        $this->assertStringContainsString('<wcf:testSetId>test-set-id</wcf:testSetId>', (string) $this->requests[0]['options']['body']);
    }

    public function testSoapFaultIsReported(): void
    {
        $result = $this->client(self::FAULT)->sendBillSync('fv1', '<Invoice/>');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('InvalidSecurity', $result->getErrorMessage());
    }

    public function testRetriesOnServerError(): void
    {
        $result = $this->client('HTTP503', self::SYNC_OK)->sendBillSync('fv1', '<Invoice/>');

        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $this->requests);
    }

    public function testNumberingRangesAreParsed(): void
    {
        $ranges = $this->client(self::RANGES)->getNumberingRanges('900123456', '900123456', 'sw-id');

        $this->assertSame('fc8eac422eba16e22ffd8c6f94b3f40a6e38162c', $ranges[0]['TechnicalKey']);
        $this->assertSame('SETP', $ranges[0]['Prefix']);
    }

    public function testGetStatusZipUnpacksApplicationResponse(): void
    {
        $zip = base64_encode(SoapClient::zip('ar1', '<ApplicationResponse>ok</ApplicationResponse>'));
        $xml = str_replace('PEFwcGxpY2F0aW9uUmVzcG9uc2UvPg==', $zip, self::SYNC_OK);

        $status = $this->client($xml)->getStatusZip('zip-key');

        $this->assertTrue($status->isValid());
        $this->assertSame('<ApplicationResponse>ok</ApplicationResponse>', $status->getApplicationResponseXml());
    }

    public function testParametersAreEscaped(): void
    {
        $this->client(self::SYNC_OK)->getStatus('a<b>&c');

        $this->assertStringContainsString('<wcf:trackId>a&lt;b&gt;&amp;c</wcf:trackId>', (string) $this->requests[0]['options']['body']);
    }

    public function testFileNamesFollowTheAnexo(): void
    {
        $date = new \DateTimeImmutable('2026-05-29');

        $this->assertSame('fv0900123456000260000002a.xml', FileNameGenerator::xml(FileNameGenerator::FACTURA, '900123456', 42, $date));
        $this->assertSame('z0900123456000260000002a.zip', FileNameGenerator::zip(FileNameGenerator::FACTURA, '900123456', 'SETP42', $date));
    }
}
