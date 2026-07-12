<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http\Middleware;

use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\RateLimiting\RateLimiterContract;

final class PublicEndpointRateLimitMiddleware implements IMiddleware
{
    private const CACHE_PREFIX = 'public_endpoint_rate_limit.';

    public function __construct(
        private readonly RateLimiterContract $limiter,
        private readonly int $defaultLimit = 60,
        private readonly int $windowSeconds = 60,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $limit = $this->limitForPath($request->uri);
        if ($limit <= 0) {
            return $next($request);
        }

        $summary = $this->limiter->consume($this->bucketKey($request), $limit, max(10, $this->windowSeconds));
        if (!$summary->allowed) {
            return Response::error('Too many requests. Please try again later.', 429)
                ->withHeader('Retry-After', (string) max(1, $summary->retryAfter))
                ->withHeader('X-RateLimit-Limit', (string) $summary->limit)
                ->withHeader('X-RateLimit-Remaining', (string) $summary->remaining)
                ->withHeader('X-RateLimit-Reset', (string) $summary->resetAt);
        }

        return $next($request)
            ->withHeader('X-RateLimit-Limit', (string) $summary->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $summary->remaining)
            ->withHeader('X-RateLimit-Reset', (string) $summary->resetAt);
    }

    private function limitForPath(string $path): int
    {
        return str_starts_with($path, '/api/') ? $this->defaultLimit : 0;
    }

    private function bucketKey(Request $request): string
    {
        return self::CACHE_PREFIX . sha1(implode('|', [
            strtoupper($request->method),
            $request->uri,
            $request->getClientIp() ?: 'unknown',
        ]));
    }
}
