<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Signer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Signer\Certificate;
use RoyaltyFusion\DianPhp\Signer\SignatureVerifier;
use RoyaltyFusion\DianPhp\Signer\XadesSigner;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;

final class XadesSignerTest extends TestCase
{
    private const UNSIGNED = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <ext:UBLExtensions>
        <ext:UBLExtension><ext:ExtensionContent><cbc:Note>dian</cbc:Note></ext:ExtensionContent></ext:UBLExtension>
        <ext:UBLExtension>
            <ext:ExtensionContent/>
        </ext:UBLExtension>
    </ext:UBLExtensions>
    <cbc:ID>SETP990000001</cbc:ID>
    <cbc:Note>Pérez &amp; Cía — ñandú</cbc:Note>
</Invoice>';

    /** @return iterable<string,array{string}> */
    public static function realDianDocuments(): iterable
    {
        foreach (glob(__DIR__ . '/../../../resources/fixtures/*.xml') ?: [] as $file) {
            $outer = (string) file_get_contents($file);
            yield basename($file) . ' (AttachedDocument)' => [$outer];
            preg_match_all('/<!\[CDATA\[(.*?)\]\]>/s', $outer, $m);
            foreach ($m[1] as $i => $inner) {
                yield basename($file) . " (embedded #{$i})" => [trim($inner)];
            }
        }
    }

    #[DataProvider('realDianDocuments')]
    public function testVerifierAcceptsRealDianApprovedSignatures(string $xml): void
    {
        $this->assertSame([], (new SignatureVerifier())->verify($xml));
    }

    public function testSignedDocumentVerifies(): void
    {
        $signed = (new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))->sign(self::UNSIGNED);

        $this->assertSame([], (new SignatureVerifier())->verify($signed));
        $this->assertStringContainsString(XadesSigner::POLICY_HASH_SHA256, $signed);
        $this->assertStringContainsString('<xades:ClaimedRole>supplier</xades:ClaimedRole>', $signed);
        $this->assertSame(1, substr_count($signed, '<ds:Signature '));
        $this->assertSame(3, substr_count($signed, '<ds:Reference '));
    }

    public function testSignatureGoesIntoTheEmptyExtensionSlot(): void
    {
        $signed = (new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))->sign(self::UNSIGNED);

        $dom = new \DOMDocument();
        $dom->loadXML($signed);
        $slots = $dom->getElementsByTagNameNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ExtensionContent');
        $this->assertSame('Note', $slots->item(0)?->firstChild?->localName);
        $this->assertSame('Signature', $slots->item(1)?->firstChild?->localName);
    }

    public function testTamperingIsDetected(): void
    {
        $signed   = (new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))->sign(self::UNSIGNED);
        $tampered = str_replace('SETP990000001', 'SETP990000002', $signed);

        $this->assertNotSame([], (new SignatureVerifier())->verify($tampered));
    }

    public function testMalformedXmlIsRejectedWithClearMessage(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no está bien formado');

        (new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))->sign('<Invoice><a>Pérez & Cía</a></Invoice>');
    }

    public function testWrongPasswordGivesClearMessage(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PKCS#12');

        Certificate::fromPkcs12File(TestCertificate::path(), 'wrong');
    }

    public function testCertificateMetadata(): void
    {
        $cert = Certificate::fromPkcs12File(TestCertificate::path(), TestCertificate::PASSWORD);

        $this->assertMatchesRegularExpression('/^\d+$/', $cert->getSerialNumber());
        $this->assertStringContainsString('C=CO', $cert->getIssuerName());
        $this->assertTrue($cert->subjectMentions('900123456'));
        $this->assertTrue($cert->isValidAt(new \DateTimeImmutable()));
        $this->assertGreaterThanOrEqual(0, $cert->daysUntilExpiration());
    }
}
