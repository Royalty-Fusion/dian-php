<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\AttachedDocument;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentParser;

/**
 * Regression tests against real DIAN-approved AttachedDocuments produced by
 * Siigo Nube for BLINDACCES SAS (NIT 901944237-8) during 2026-Q1 and
 * 2026-Q2. The fixtures cover the three most common patterns in Colombian
 * production traffic:
 *
 *   FV3   Factura nacional (estándar, sin retenciones)
 *   FV5   Factura de exportación a Guatemala (USD/COP con PaymentExchangeRate)
 *   FV7   Factura nacional con retenciones (ReteRenta + ReteICA)
 *   NC1   Nota Crédito nacional de bajo monto
 *   NC2   Nota Crédito de exportación
 *   NC3   Nota Crédito nacional (sobre FV7 probable)
 */
final class SiigoGoldenMastersTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../../../resources/fixtures';

    /** @return array<string, array{0:string,1:string,2:string,3:int,4:string}> */
    public static function fixtureProvider(): array
    {
        // name => [filename, parentDocId, customizationId, ublVersionExpected, schemeName]
        return [
            'FV3 nacional sin retenciones' => ['siigo-blindacces-FV3.xml', 'FV3', '10',  21063000, 'CUFE-SHA384'],
            'FV5 exportación USD/COP'      => ['siigo-blindacces-FV5.xml', 'FV5', '10',  74390089, 'CUFE-SHA384'],
            'FV7 nacional con retenciones' => ['siigo-blindacces-FV7.xml', 'FV7', '10',  20384700, 'CUFE-SHA384'],
            // Siigo numera las NCs como prefijo "NC2" + secuencia 1..3 → NC21, NC22, NC23.
            'NC21 nacional simple'           => ['siigo-blindacces-NC1.xml', 'NC21', '20',   1000000, 'CUFE-SHA384'],
            'NC22 exportación'               => ['siigo-blindacces-NC2.xml', 'NC22', '20',  75000000, 'CUFE-SHA384'],
            'NC23 nacional'                  => ['siigo-blindacces-NC3.xml', 'NC23', '20',  20384700, 'CUFE-SHA384'],
        ];
    }

    /** @dataProvider fixtureProvider */
    public function testEveryFixtureIsAcceptedAndExposesEmbeddedDocs(
        string $filename,
        string $expectedParentDocId,
        string $expectedCustomizationId,
        int $expectedTotalCop,
        string $expectedSchemeName,
    ): void {
        $path = self::FIXTURES_DIR . '/' . $filename;
        $this->assertFileExists($path);

        $parser = new AttachedDocumentParser();
        $doc    = $parser->parse((string) file_get_contents($path));

        // Envelope-level
        $this->assertSame(
            $expectedParentDocId,
            $doc->getParentDocumentId(),
            "ParentDocumentID for $filename"
        );
        $this->assertSame(96, strlen($doc->getId()), "CUFE/CUDE length for $filename");
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $doc->getId());

        // Embedded payloads must be non-empty
        $invoice = $doc->getSignedInvoiceXml();
        $ar      = $doc->getApplicationResponseXml();
        $this->assertNotEmpty($invoice, "Inner Invoice/CN missing in $filename");
        $this->assertNotEmpty($ar,      "ApplicationResponse missing in $filename");

        // Approved by DIAN
        $this->assertTrue(
            $parser->isAccepted($doc),
            "$filename should be DIAN-approved (ResponseCode=02 expected)."
        );

        // Inner document structural checks
        $this->assertStringContainsString("<cbc:CustomizationID>{$expectedCustomizationId}</cbc:CustomizationID>", $invoice);
        $this->assertStringContainsString('<cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>', $invoice);
        $this->assertStringContainsString("schemeName=\"{$expectedSchemeName}\"", $invoice);
        $this->assertStringContainsString('schemeID="1"', $invoice);  // Siigo always uses 1
        $this->assertStringContainsString('<cbc:DocumentCurrencyCode>COP</cbc:DocumentCurrencyCode>', $invoice);

        // Total roughly matches (PayableAmount in COP, ignoring cents)
        if (preg_match('/<cbc:PayableAmount[^>]*>([0-9.]+)<\/cbc:PayableAmount>/', $invoice, $m)) {
            $actual = (int) floor((float) $m[1]);
            $this->assertEqualsWithDelta(
                $expectedTotalCop,
                $actual,
                1.0,
                "PayableAmount mismatch for $filename"
            );
        } else {
            $this->fail("Could not find PayableAmount in $filename");
        }
    }

    public function testFV5RealExportInvoiceCarriesPaymentExchangeRate(): void
    {
        $doc = (new AttachedDocumentParser())->parse(
            (string) file_get_contents(self::FIXTURES_DIR . '/siigo-blindacces-FV5.xml')
        );
        $invoice = $doc->getSignedInvoiceXml();

        $this->assertStringContainsString('<cac:PaymentExchangeRate>', $invoice);
        $this->assertStringContainsString('<cbc:SourceCurrencyCode>COP</cbc:SourceCurrencyCode>', $invoice);
        $this->assertStringContainsString('<cbc:TargetCurrencyCode>USD</cbc:TargetCurrencyCode>', $invoice);
        $this->assertStringContainsString('<cbc:CalculationRate>3669.96</cbc:CalculationRate>', $invoice);

        // Cliente Guatemala
        $this->assertStringContainsString('<cbc:IdentificationCode>GT</cbc:IdentificationCode>', $invoice);
        $this->assertStringContainsString('Ciudad de Guatemala', $invoice);
    }

    public function testFV7NationalInvoiceCarriesWithholdingTaxTotalsForReteRentaAndReteICA(): void
    {
        $doc = (new AttachedDocumentParser())->parse(
            (string) file_get_contents(self::FIXTURES_DIR . '/siigo-blindacces-FV7.xml')
        );
        $invoice = $doc->getSignedInvoiceXml();

        // Two separate WithholdingTaxTotal blocks: ReteRenta (06) + ReteICA (07)
        $count = substr_count($invoice, '<cac:WithholdingTaxTotal>');
        $this->assertSame(2, $count, 'FV7 must emit exactly 2 WithholdingTaxTotal blocks.');

        // ReteRenta (tributo 06) — DIAN naming sometimes "ReteRenta" sometimes "ReteFuente"
        $this->assertMatchesRegularExpression('/<cbc:ID>06<\/cbc:ID>\s*<cbc:Name>Rete(Renta|Fuente)<\/cbc:Name>/', $invoice);

        // ReteICA (tributo 07)
        $this->assertMatchesRegularExpression('/<cbc:ID>07<\/cbc:ID>\s*<cbc:Name>ReteICA<\/cbc:Name>/', $invoice);
    }

    public function testNoteFixturesCarryBillingReferenceToOriginalInvoice(): void
    {
        foreach (['NC1', 'NC2', 'NC3'] as $note) {
            $path = self::FIXTURES_DIR . "/siigo-blindacces-{$note}.xml";
            $doc  = (new AttachedDocumentParser())->parse((string) file_get_contents($path));
            $cn = $doc->getSignedInvoiceXml();

            $this->assertStringContainsString(
                '<cac:BillingReference>',
                $cn,
                "$note should reference the original invoice in <cac:BillingReference>"
            );
            $this->assertStringContainsString(
                '<cbc:CreditNoteTypeCode>91</cbc:CreditNoteTypeCode>',
                $cn,
                "$note must declare CreditNoteTypeCode=91"
            );
            $this->assertStringContainsString(
                '<cac:DiscrepancyResponse>',
                $cn,
                "$note must include the DiscrepancyResponse block"
            );
        }
    }

    public function testAllFixturesShareTheSameSupplier(): void
    {
        // All 6 fixtures come from BLINDACCES SAS — sanity check.
        foreach (self::fixtureProvider() as $name => $row) {
            $filename = $row[0];
            $invoice = (new AttachedDocumentParser())
                ->parse((string) file_get_contents(self::FIXTURES_DIR . '/' . $filename))
                ->getSignedInvoiceXml();
            $this->assertStringContainsString('BLINDACCES', $invoice, "Supplier mismatch in $name");
            $this->assertStringContainsString('901944237', $invoice, "Supplier NIT mismatch in $name");
        }
    }
}
