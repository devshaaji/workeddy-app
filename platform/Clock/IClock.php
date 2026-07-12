<?php

/**
 * Clock interface — abstracts system time.
 *
 * All production code that needs "now" should inject IClock
 * instead of calling new DateTimeImmutable() directly.
 *
 * This enables deterministic testing and consistent timestamps
 * within a single request/job execution.
 */

declare(strict_types=1);

namespace WorkEddy\Platform\Clock;

interface IClock
{
    /** Get the current time. */
    public function now(): \DateTimeImmutable;
}
