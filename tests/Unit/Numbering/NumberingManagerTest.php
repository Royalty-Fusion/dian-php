<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Numbering;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Numbering\InMemoryNumberingStore;
use RoyaltyFusion\DianPhp\Numbering\NumberingException;
use RoyaltyFusion\DianPhp\Numbering\NumberingManager;
use RoyaltyFusion\DianPhp\Numbering\NumberingStore;
use RoyaltyFusion\DianPhp\Numbering\PdoNumberingStore;

final class NumberingManagerTest extends TestCase
{
    private static function resolution(string $from = '990000000', string $to = '990000004'): Resolution
    {
        return (new Resolution())->setNumber('18760000001')->setPrefix('SETP')->setFrom($from)->setTo($to)
            ->setDateFrom(new \DateTimeImmutable('2026-01-01'))->setDateTo(new \DateTimeImmutable('2026-12-31'));
    }

    /** @return array<string,array{NumberingStore}> */
    public static function stores(): array
    {
        $pdo = new PdoNumberingStore(new \PDO('sqlite::memory:'));
        $pdo->createTable();

        return ['memoria' => [new InMemoryNumberingStore()], 'pdo sqlite' => [$pdo]];
    }

    #[DataProvider('stores')]
    public function testHandsOutTheRangeInOrderAndStopsAtTheEnd(NumberingStore $store): void
    {
        $manager = new NumberingManager($store);
        $date    = new \DateTimeImmutable('2026-06-01');
        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $manager->next(self::resolution(), $date);
        }
        $this->assertSame(['990000000', '990000001', '990000002', '990000003', '990000004'], $numbers);
        $this->assertSame(0, $manager->status(self::resolution(), $date)['remaining']);
        $this->assertSame(['Resolución 18760000001 (SETP990000000-990000004): rango agotado.'], $manager->alerts(self::resolution(), $date));

        $this->expectException(NumberingException::class);
        $this->expectExceptionMessage('Se agotó el rango');
        $manager->next(self::resolution(), $date);
    }

    #[DataProvider('stores')]
    public function testMigrationContinuesFromTheLastUsedNumber(NumberingStore $store): void
    {
        $manager = new NumberingManager($store);
        $manager->setLastUsed(self::resolution('1', '5000'), 1234);

        $this->assertSame('1235', $manager->next(self::resolution('1', '5000'), new \DateTimeImmutable('2026-06-01')));
        // Each resolution/range keeps its own counter.
        $this->assertSame('990000000', $manager->next(self::resolution(), new \DateTimeImmutable('2026-06-01')));
    }

    public function testRefusesNumbersOutsideTheValidity(): void
    {
        $manager = new NumberingManager(new InMemoryNumberingStore());

        try {
            $manager->next(self::resolution(), new \DateTimeImmutable('2027-01-01T08:00:00-05:00'));
            $this->fail('Expired resolution must be refused.');
        } catch (NumberingException $e) {
            $this->assertStringContainsString('venció el 2026-12-31', $e->getMessage());
        }
        $this->expectException(NumberingException::class);
        $manager->next(self::resolution(), new \DateTimeImmutable('2025-12-31'));
    }

    public function testAlertsBeforeRunningOutOrExpiring(): void
    {
        $manager = new NumberingManager(new InMemoryNumberingStore(), minRemaining: 10, minDays: 30);
        $resolution = self::resolution('1', '100');
        $manager->setLastUsed($resolution, 95);

        $status = $manager->status($resolution, new \DateTimeImmutable('2026-12-11T09:00:00-05:00'));
        $this->assertSame(5, $status['remaining']);
        $this->assertSame(95.0, $status['used_percent']);
        $this->assertSame(20, $status['days_left']);
        $this->assertSame([
            'Resolución 18760000001 (SETP1-100): quedan 5 números (95% usado).',
            'Resolución 18760000001 (SETP1-100): vence en 20 días (2026-12-31).',
        ], $manager->alerts($resolution, new \DateTimeImmutable('2026-12-11T09:00:00-05:00')));
        $this->assertSame([], (new NumberingManager(new InMemoryNumberingStore()))->alerts(self::resolution('1', '5000'), new \DateTimeImmutable('2026-06-01')));
    }

    public function testTwoConnectionsToTheSameDatabaseNeverShareANumber(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'dian-numbering');
        try {
            $a = new PdoNumberingStore(new \PDO('sqlite:' . $file));
            $b = new PdoNumberingStore(new \PDO('sqlite:' . $file));
            $a->createTable();

            $managers = [new NumberingManager($a), new NumberingManager($b)];
            $numbers  = [];
            for ($i = 0; $i < 20; $i++) {
                $numbers[] = $managers[$i % 2]->next(self::resolution('1', '1000'), new \DateTimeImmutable('2026-06-01'));
            }
            $this->assertSame(array_map('strval', range(1, 20)), $numbers);
        } finally {
            @unlink((string) $file);
        }
    }

    public function testInvalidRange(): void
    {
        $this->expectException(NumberingException::class);
        (new NumberingManager(new InMemoryNumberingStore()))->next(self::resolution('10', '5'));
    }
}
