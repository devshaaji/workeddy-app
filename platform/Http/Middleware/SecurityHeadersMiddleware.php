<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http\Middleware;

use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;

final class SecurityHeadersMiddleware implements IMiddleware
{
    /**
     * Routes that legitimately render their own content inside an
     * same-origin <iframe> on an authenticated admin page (e.g. the file
     * manager's inline PDF preview). Everything else keeps the strict
     * frame-ancestors 'none' / X-Frame-Options: DENY clickjacking defence.
     *
     * @var list<string>
     */
    private const INLINE_PREVIEW_PATH_SUFFIXES = [
        '/view',
    ];

    private const INLINE_PREVIEW_PATH_PREFIXES = [
        '/api/v1/storage/files/',
        '/api/v1/files/',
    ];

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if ($this->isInlinePreviewRoute($request)) {
            return $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('X-Frame-Options', 'SAMEORIGIN')
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
                ->withHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'self'");
        }

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->withHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'");

        if ($this->hstsEnabled()) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Only same-origin GET requests to a known file-preview endpoint
     * (…/view) are allowed to be framed. Downloads, uploads, and every
     * other route are unaffected.
     */
    private function isInlinePreviewRoute(Request $request): bool
    {
        if ($request->getMethod() !== 'GET') {
            return false;
        }

        $uri = $request->getUri();
        $hasKnownPrefix = false;
        foreach (self::INLINE_PREVIEW_PATH_PREFIXES as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                $hasKnownPrefix = true;
                break;
            }
        }
        if (!$hasKnownPrefix) {
            return false;
        }

        foreach (self::INLINE_PREVIEW_PATH_SUFFIXES as $suffix) {
            if (str_ends_with($uri, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function hstsEnabled(): bool
    {
        $env = strtolower((string) ($_ENV['APP_ENV'] ?? 'production'));
        $secure = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $env === 'production' && $secure;
    }
}
