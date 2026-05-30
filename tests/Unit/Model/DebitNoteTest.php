<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\DebitNote;

/**
 * Regression test for the typo in DebitNote::getClient(): ?client
 * that was fixed during the Phase 0 reorg.
 */
final class DebitNoteTest extends TestCase
{
    public function testGetClientReturnTypeIsResolved(): void
    {
        $note = new DebitNote();
        $this->assertNull($note->getClient());

        $client = (new Client())->setNumeroDocumento('12345');
        $note->setClient($client);
        $this->assertSame($client, $note->getClient());
    }
}
