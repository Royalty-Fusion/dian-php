<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Ws;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Ws\SoapClient;

/**
 * Smoke checks on the public API surface of SoapClient — confirms the new
 * GetNumberingRange / GetXmlByDocumentKey methods exist and are callable.
 * Real DIAN traffic is exercised manually via the dian:status console
 * command and the integration test in tests/Integration/.
 */
final class SoapClientApiTest extends TestCase
{
    public function testGetNumberingRangeMethodSignature(): void
    {
        $r = new \ReflectionClass(SoapClient::class);
        $this->assertTrue($r->hasMethod('getNumberingRange'));
        $m = $r->getMethod('getNumberingRange');
        $this->assertCount(3, $m->getParameters());
        $this->assertSame('accountCode', $m->getParameters()[0]->getName());
    }

    public function testGetXmlByDocumentKeyMethodSignature(): void
    {
        $r = new \ReflectionClass(SoapClient::class);
        $this->assertTrue($r->hasMethod('getXmlByDocumentKey'));
        $m = $r->getMethod('getXmlByDocumentKey');
        $this->assertCount(1, $m->getParameters());
        $this->assertSame('trackId', $m->getParameters()[0]->getName());
        $this->assertSame('string', (string) $m->getReturnType());
    }

    public function testGetStatusAndGetStatusZipRemainAvailable(): void
    {
        $r = new \ReflectionClass(SoapClient::class);
        $this->assertTrue($r->hasMethod('getStatus'));
        $this->assertTrue($r->hasMethod('getStatusZip'));
    }
}
