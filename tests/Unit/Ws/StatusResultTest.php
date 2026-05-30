<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Ws;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Ws\StatusResult;

final class StatusResultTest extends TestCase
{
    public function testFieldsRoundTrip(): void
    {
        $r = (new StatusResult())
            ->setIsValid(true)
            ->setStatusCode('00')
            ->setStatusDescription('Procesado Correctamente')
            ->setStatusMessage('OK')
            ->setApplicationResponseXml('<ApplicationResponse/>')
            ->setErrorMessages(['err 1', 'err 2']);

        $this->assertTrue($r->isValid());
        $this->assertSame('00', $r->getStatusCode());
        $this->assertSame('Procesado Correctamente', $r->getStatusDescription());
        $this->assertSame('OK', $r->getStatusMessage());
        $this->assertSame('<ApplicationResponse/>', $r->getApplicationResponseXml());
        $this->assertSame(['err 1', 'err 2'], $r->getErrorMessages());
    }
}
