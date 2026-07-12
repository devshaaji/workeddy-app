<?php

/**
 * Frozen clock — testing implementation.
 *
 * Always returns the same fixed time. Allows deterministic tests
 * and controlled time advancement.
 */

declare(strict_types=1);

namespace WorkEddy\Platform\Clock;

final class FrozenClock implements IClock
{
    private \DateTimeImmutable $frozenAt;

    public function __construct(?\DateTimeImmutable $frozenAt = null)
    {
        $this->frozenAt = $frozenAt ?? new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->frozenAt;
    }

    /**
     * Set the frozen time.
     */
    public function setTo(\DateTimeImmutable $time): void
    {
        $this->frozenAt = $time;
    }

    /**
     * Advance time by a given interval.
     */
    public function advance(\DateInterval $interval): void
    {
        $this->frozenAt = $this->frozenAt->add($interval);
    }
}
