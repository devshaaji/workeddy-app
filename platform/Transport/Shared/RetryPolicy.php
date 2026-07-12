<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

final class RetryPolicy
{
    /**
     * @param list<int> $backoffSeconds
     */
    public function __construct(private readonly array $backoffSeconds = [30, 120, 300, 900]) {}

    public function canRetry(int $attemptCount, int $maxAttempts): bool
    {
        return $maxAttempts === 0 || ($attemptCount + 1) < $maxAttempts;
    }

    public function delaySeconds(int $attemptCount): int
    {
        if ($this->backoffSeconds === []) {
            return 30;
        }

        $index = min(max(0, $attemptCount), count($this->backoffSeconds) - 1);

        return max(1, (int) $this->backoffSeconds[$index]);
    }
}
