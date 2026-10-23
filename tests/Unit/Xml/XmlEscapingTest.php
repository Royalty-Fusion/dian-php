<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Tests\Support\DocumentFactory;
use RoyaltyFusion\DianPhp\Xml\TwigFactory;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

final class XmlEscapingTest extends TestCase
{
    public function testSpecialCharactersAreEscapedAndCannotInjectNodes(): void
    {
        $doc = DocumentFactory::invoice();
        $doc->getItems()[0]->setDescripcion('Tornillo</cbc:Description><cbc:Inyectado>x</cbc:Inyectado><cbc:Description>');

        $xml = (new XmlBuilder())->build($doc, str_repeat('a', 96), 'qr');
        $dom = new \DOMDocument();

        $this->assertTrue($dom->loadXML($xml));
        $this->assertStringNotContainsString('<cbc:Inyectado>', $xml);
        $this->assertStringContainsString('Pérez &amp; Cía &lt;Tecnología&gt; S.A.S.', $xml);
        $this->assertSame(
            'Tornillo</cbc:Description><cbc:Inyectado>x</cbc:Inyectado><cbc:Description>',
            $dom->getElementsByTagNameNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'Description')->item(0)?->textContent
        );
    }

    public function testControlCharactersAreRemoved(): void
    {
        $this->assertSame('ab', TwigFactory::escape("a\x0Bb\x00"));
    }

    public function testCdataSplitsTerminator(): void
    {
        $cdata = TwigFactory::cdata('<x>]]></x>');
        $dom   = new \DOMDocument();

        $this->assertTrue($dom->loadXML('<r>' . $cdata . '</r>'));
        $this->assertSame('<x>]]></x>', $dom->documentElement?->textContent);
    }
}
