<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\AttachedDocument;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocument;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentBuilder;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentParser;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;

/**
 * Golden-master test against the real Siigo AttachedDocument vendored at
 * resources/fixtures/siigo-blindacces-FV5.xml.
 */
final class AttachedDocumentParserTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../resources/fixtures/siigo-blindacces-FV5.xml';

    public function testParsesRealSiigoAttachedDocument(): void
    {
        $this->assertFileExists(self::FIXTURE);

        $xml    = (string) file_get_contents(self::FIXTURE);
        $parser = new AttachedDocumentParser();
        $doc    = $parser->parse($xml);

        // Envelope metadata
        $this->assertSame(
            'a7516114373f44f2160cca5d079afbb8bc865db2c249e4699b19c2de2d5ef9890d9955584f6e86cb49807bbbfd78a61e',
            $doc->getId()
        );
        $this->assertSame('FV5', $doc->getParentDocumentId());
        $this->assertSame('Documentos adjuntos', $doc->getCustomizationId());
        $this->assertSame('Factura Electrónica de Venta', $doc->getProfileId());
        $this->assertSame('1', $doc->getProfileExecutionId());
        $this->assertSame('2026-03-31', $doc->getIssueDate()?->format('Y-m-d'));

        // Embedded payloads
        $this->assertNotEmpty($doc->getSignedInvoiceXml());
        $this->assertNotEmpty($doc->getApplicationResponseXml());
        $this->assertStringContainsString('<Invoice', $doc->getSignedInvoiceXml());
        $this->assertStringContainsString('BLINDACCES SAS', $doc->getSignedInvoiceXml());
        $this->assertStringContainsString('HORNO DE CURVADO', $doc->getSignedInvoiceXml());
        $this->assertStringContainsString('<ApplicationResponse', $doc->getApplicationResponseXml());

        // The embedded Invoice carries Siigo's chosen schemeID="1"
        $this->assertStringContainsString('<cbc:UUID schemeID="1" schemeName="CUFE-SHA384">', $doc->getSignedInvoiceXml());
    }

    public function testRoundtripBuilderProducesParseableDocument(): void
    {
        $built = (new AttachedDocumentBuilder())->build(
            (new AttachedDocument())
                ->setId('cufe-test')
                ->setParentDocumentId('FV5')
                ->setIssueDate(new \DateTimeImmutable('2026-03-31T14:55:52+00:00'))
                ->setSender((new Company())
                    ->setNit('901944237')->setRazonSocial('BLINDACCES SAS')
                    ->setTipoDocumento('31')->setResponsabilidades('R-99-PN'))
                ->setReceiver((new Client())
                    ->setNumeroDocumento('117750735')->setRazonSocial('BLINDACCES SA')
                    ->setTipoDocumento('50')->setResponsabilidades('R-99-PN'))
                ->setSignedInvoiceXml('<Invoice><dummy/></Invoice>')
                ->setApplicationResponseXml('<ApplicationResponse><cbc:ResponseCode xmlns:cbc="urn:cbc">02</cbc:ResponseCode></ApplicationResponse>')
        );

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($built));
        $this->assertSame('AttachedDocument', $dom->documentElement->localName);

        $parser  = new AttachedDocumentParser();
        $reparsed = $parser->parse($built);

        $this->assertSame('cufe-test',                   $reparsed->getId());
        $this->assertSame('FV5',                         $reparsed->getParentDocumentId());
        $this->assertStringContainsString('<Invoice>',   $reparsed->getSignedInvoiceXml());
        $this->assertStringContainsString('<ApplicationResponse>', $reparsed->getApplicationResponseXml());
        $this->assertTrue($parser->isAccepted($reparsed));
    }

    public function testRejectsNonAttachedDocumentRoot(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Root element is not/i');
        (new AttachedDocumentParser())->parse('<?xml version="1.0"?><Invoice/>');
    }

    public function testIsAcceptedReturnsTrueForRealApprovedFixture(): void
    {
        $xml = (string) file_get_contents(self::FIXTURE);
        $doc = (new AttachedDocumentParser())->parse($xml);
        // The Siigo fixture's embedded ApplicationResponse carries
        // <cbc:ResponseCode>02</cbc:ResponseCode> — i.e. DIAN-approved.
        $this->assertTrue((new AttachedDocumentParser())->isAccepted($doc));
    }

    public function testIsAcceptedReturnsFalseWhenApplicationResponseMissing(): void
    {
        $doc = new AttachedDocument();
        $this->assertFalse((new AttachedDocumentParser())->isAccepted($doc));
    }
}
