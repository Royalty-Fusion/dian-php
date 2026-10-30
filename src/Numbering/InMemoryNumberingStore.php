<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Numbering;

/** Store for tests and single-process scripts (state is lost at the end). */
final class InMemoryNumberingStore implements NumberingStore
{
    /** @var array<string,int> */
    private array $last = [];

    public function next(string $key, int $from, int $to): ?int
    {
        $next = isset($this->last[$key]) ? max($this->last[$key] + 1, $from) : $from;
        if ($next > $to) {
            return null;
        }

        return $this->last[$key] = $next;
    }

    public function current(string $key): ?int
    {
        return $this->last[$key] ?? null;
    }

    public function set(string $key, int $last): void
    {
        $this->last[$key] = $last;
    }
}
