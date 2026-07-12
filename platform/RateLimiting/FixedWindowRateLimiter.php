<?php

declare(strict_types=1);

namespace WorkEddy\Platform\RateLimiting;

use WorkEddy\Platform\Cache\ICacheService;

final class FixedWindowRateLimiter implements RateLimiterContract
{
    public function __construct(
        private readonly ICacheService $cache,
        private readonly ?\Closure $now = null,
    ) {}

    public function consume(string $key, int $limit, int $windowSeconds): RateLimitResult
    {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $now = $this->now();
        $cacheKey = 'rate_limit.' . sha1($key);

        $state = $this->cache->get($cacheKey);
        if (!is_array($state) || ($state['reset_at'] ?? 0) <= $now) {
            $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $count = ((int) $state['count']) + 1;
        $resetAt = (int) $state['reset_at'];
        $allowed = $count <= $limit;
        $remaining = max(0, $limit - $count);
        $retryAfter = $allowed ? 0 : max(1, $resetAt - $now);

        $this->cache->set($cacheKey, ['count' => $count, 'reset_at' => $resetAt], max(1, $resetAt - $now));

        return new RateLimitResult(
            allowed: $allowed,
            limit: $limit,
            remaining: $remaining,
            resetAt: $resetAt,
            retryAfter: $retryAfter,
            count: $count,
        );
    }

    private function now(): int
    {
        return $this->now instanceof \Closure ? (int) ($this->now)() : time();
    }
}
