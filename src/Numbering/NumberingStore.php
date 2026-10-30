<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Numbering;

/**
 * Persistence of the last consecutive used per numbering range. Implementations
 * must be atomic: two concurrent requests never get the same number.
 */
interface NumberingStore
{
    /**
     * Atomically takes the next number of the range: $from the first time,
     * then last + 1. Returns null when the range is exhausted (last = $to).
     */
    public function next(string $key, int $from, int $to): ?int;

    /** Last number handed out for the range, null if none yet. */
    public function current(string $key): ?int;

    /**
     * Sets the last used number, e.g. when migrating from another system
     * (the next call to next() returns $last + 1).
     */
    public function set(string $key, int $last): void;
}
