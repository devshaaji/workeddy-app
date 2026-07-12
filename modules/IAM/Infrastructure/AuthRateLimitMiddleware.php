<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Platform\Http\ExceptionHandler;
use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\RateLimiting\RateLimitResult;
use WorkEddy\Platform\RateLimiting\RateLimiterContract;

final class AuthRateLimitMiddleware implements IMiddleware
{
    public function __construct(
        private readonly RateLimiterContract $limiter,
        private readonly IAMSettings $settings,
        private readonly ?ExceptionHandler $exceptionHandler = null,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $limits = $this->limitsForPath($request->getUri());
        if ($limits['ip'] <= 0) {
            return $next($request);
        }

        $results = [
            $this->limiter->consume($this->ipBucketKey($request), $limits['ip'], $this->windowSeconds()),
        ];

        $accountKey = $this->accountBucketKey($request);
        if ($accountKey !== null && $limits['account'] > 0) {
            $results[] = $this->limiter->consume($accountKey, $limits['account'], $this->windowSeconds());
        }

        $summary = $this->summarize($results);

        if (!$summary['allowed']) {
            return Response::error('Too many authentication attempts. Please try again later.', 429)
                ->withHeader('Retry-After', (string) max(1, $summary['retryAfter']))
                ->withHeader('X-RateLimit-Limit', (string) $summary['limit'])
                ->withHeader('X-RateLimit-Remaining', (string) $summary['remaining'])
                ->withHeader('X-RateLimit-Reset', (string) $summary['resetAt']);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            if ($this->exceptionHandler === null) {
                throw $e;
            }

            $response = $this->exceptionHandler->handle($e);
        }

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $summary['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $summary['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $summary['resetAt']);
    }

    /**
     * @return array{ip:int, account:int}
     */
    private function limitsForPath(string $path): array
    {
        return match ($path) {
            '/api/v1/auth/login' => [
                'ip' => $this->settings->authRateLimitLoginIp(),
                'account' => $this->settings->authRateLimitLoginAccount(),
            ],
            '/api/v1/auth/register' => [
                'ip' => $this->settings->authRateLimitRegisterIp(),
                'account' => $this->settings->authRateLimitRegisterAccount(),
            ],
            '/api/v1/auth/forgot-password',
            '/api/v1/auth/reset-password' => [
                'ip' => $this->settings->authRateLimitPasswordIp(),
                'account' => $this->settings->authRateLimitPasswordAccount(),
            ],
            '/api/v1/auth/resend-otp',
            '/api/v1/auth/verify-otp' => [
                'ip' => $this->settings->authRateLimitOtpIp(),
                'account' => $this->settings->authRateLimitOtpAccount(),
            ],
            default => ['ip' => 0, 'account' => 0],
        };
    }

    private function windowSeconds(): int
    {
        return $this->settings->authRateLimitWindowSeconds();
    }

    /**
     * @param list<RateLimitResult> $results
     * @return array{allowed:bool, limit:int, remaining:int, resetAt:int, retryAfter:int}
     */
    private function summarize(array $results): array
    {
        $summary = $this->toSummary($results[0]);
        foreach ($results as $result) {
            $current = $this->toSummary($result);
            if (!$current['allowed']) {
                $summary = $current;
                break;
            }
            if ($current['remaining'] < $summary['remaining']) {
                $summary = $current;
            }
        }

        $summary['allowed'] = !array_filter($results, static fn(RateLimitResult $result): bool => !$result->allowed);

        return $summary;
    }

    /**
     * @return array{allowed:bool, limit:int, remaining:int, resetAt:int, retryAfter:int}
     */
    private function toSummary(RateLimitResult $result): array
    {
        return [
            'allowed' => $result->allowed,
            'limit' => $result->limit,
            'remaining' => $result->remaining,
            'resetAt' => $result->resetAt,
            'retryAfter' => $result->retryAfter,
        ];
    }

    private function ipBucketKey(Request $request): string
    {
        $ip = $request->getClientIp() ?: 'unknown';

        return 'ip|' . strtolower($request->getMethod()) . '|' . $request->getUri() . '|' . $ip;
    }

    private function accountBucketKey(Request $request): ?string
    {
        $body = array_replace($request->body, $request->json);
        $value = match ($request->getUri()) {
            '/api/v1/auth/login',
            '/api/v1/auth/register' => $body['email'] ?? $body['username'] ?? null,
            '/api/v1/auth/reset-password',
            '/api/v1/auth/forgot-password' => $body['identifier'] ?? $body['email'] ?? $body['username'] ?? null,
            '/api/v1/auth/resend-otp',
            '/api/v1/auth/verify-otp' => $body['userId'] ?? $body['user_id'] ?? null,
            default => null,
        };

        $normalized = $this->normalizeAccountIdentifier($value);
        if ($normalized === null) {
            return null;
        }

        return 'account|' . strtolower($request->getMethod()) . '|' . $request->getUri() . '|' . $normalized;
    }

    private function normalizeAccountIdentifier(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $identifier = strtolower(trim((string) $value));
        return $identifier !== '' ? $identifier : null;
    }
}
