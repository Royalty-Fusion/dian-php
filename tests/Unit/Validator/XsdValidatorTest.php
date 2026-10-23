<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Signer\XadesSigner;
use RoyaltyFusion\DianPhp\Tests\Support\DocumentFactory;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
use RoyaltyFusion\DianPhp\Validator\XsdValidator;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;
use RoyaltyFusion\DianPhp\Xml\QrGenerator;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

final class XsdValidatorTest extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function realDianDocuments(): iterable
    {
        foreach (glob(__DIR__ . '/../../../resources/fixtures/*.xml') ?: [] as $file) {
            $outer = (string) file_get_contents($file);
            yield basename($file) => [$outer];
            preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $outer, $m);
            yield basename($file) . ' (embedded)' => [trim($m[1])];
        }
    }

    #[DataProvider('realDianDocuments')]
    public function testBundledSchemasAcceptRealDianApprovedDocuments(string $xml): void
    {
        $this->assertSame([], (new XsdValidator())->validate($xml)->messages());
    }

    /** @return iterable<string,array{Invoice|CreditNote|DebitNote}> */
    public static function generatedDocuments(): iterable
    {
        yield 'factura' => [DocumentFactory::invoice()];
        yield 'nota crédito' => [DocumentFactory::creditNote()];
        yield 'nota crédito sin referencia' => [DocumentFactory::creditNote(false)];
        yield 'nota débito' => [DocumentFactory::debitNote()];
    }

    #[DataProvider('generatedDocuments')]
    public function testSignedSdkDocumentsAreSchemaValid(Invoice|CreditNote|DebitNote $doc): void
    {
        $uuid = (new CufeGenerator())->generate($doc, 'key');
        $xml  = (new XmlBuilder())->build($doc, $uuid, (new QrGenerator())->content($doc, $uuid));
        $signed = (new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))->sign($xml);

        $this->assertSame([], (new XsdValidator())->validate($signed)->messages());
    }

    public function testDetectsStructuralErrors(): void
    {
        $doc  = DocumentFactory::invoice();
        $xml  = (new XmlBuilder())->build($doc, 'x', 'qr');
        $xml  = str_replace('<cbc:LineCountNumeric>', '<cbc:Inventado>1</cbc:Inventado><cbc:LineCountNumeric>', $xml);

        $result = (new XsdValidator())->validate($xml);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('cbc:Inventado', implode("\n", $result->messages()));
    }

    public function testLongCertificateSerialNumbersAreAccepted(): void
    {
        $doc    = DocumentFactory::invoice();
        $signed = (new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))->sign((new XmlBuilder())->build($doc, 'x', 'qr'));
        $signed = (string) preg_replace('#<ds:X509SerialNumber>\d+</ds:X509SerialNumber>#', '<ds:X509SerialNumber>77731540194324033225762167708893181572760167714</ds:X509SerialNumber>', $signed);

        $this->assertSame([], (new XsdValidator())->validate($signed)->messages());
    }

    public function testMalformedXmlIsReported(): void
    {
        $result = (new XsdValidator())->validate('<Invoice><a>Pérez & Cía</a></Invoice>');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('XML_001', implode("\n", $result->messages()));
    }
}
