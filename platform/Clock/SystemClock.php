<?php

/**
 * System clock — production implementation.
 *
 * Returns real wall-clock time. Used in all non-test environments.
 */

declare(strict_types=1);

namespace WorkEddy\Platform\Clock;

final class SystemClock implements IClock
{
    private string $timezone;

    public function __construct(?string $timezone = null)
    {
        $this->timezone = $timezone ?? ($_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos');
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
    }
}
