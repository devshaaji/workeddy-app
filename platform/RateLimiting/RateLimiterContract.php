<?php

declare(strict_types=1);

namespace WorkEddy\Platform\RateLimiting;

interface RateLimiterContract
{
    public function consume(string $key, int $limit, int $windowSeconds): RateLimitResult;
}
