<?php

/**
 * CORS middleware — infrastructure concern.
 *
 * Adds Access-Control headers to responses.
 * Handles preflight OPTIONS requests.
 * No business logic.
 *
 * SECURITY: Never allows credentials with wildcard ('*') origins.
 * When specific origins are configured, validates the request Origin
 * against the allowlist and reflects the matched origin.
 */

declare(strict_types=1);

namespace WorkEddy\Platform\Http\Middleware;

use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;

final class CorsMiddleware implements IMiddleware
{
    /**
     * @param string $allowedOrigins Comma-separated origins or '*'.
     * @param string $allowedMethods Comma-separated methods.
     * @param string $allowedHeaders Comma-separated headers.
     */
    public function __construct(
        private readonly string $allowedOrigins = '',
        private readonly string $allowedMethods = 'GET, POST, PUT, DELETE, OPTIONS',
        private readonly string $allowedHeaders = 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token',
    ) {}

    public function process(Request $request, callable $next): Response
    {
        // Handle preflight
        if ($request->getMethod() === 'OPTIONS') {
            return $this->addCorsHeaders($request, new Response('', 204));
        }

        $response = $next($request);
        return $this->addCorsHeaders($request, $response);
    }

    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = trim((string) ($request->header('Origin') ?? ''));
        $allowedOrigin = $this->allowedOrigin($origin);
        if ($allowedOrigin === null) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', $this->allowedMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowedHeaders)
            ->withHeader('Access-Control-Max-Age', '86400');

        // Only allow credentials when a specific origin is matched (never with wildcard)
        if ($allowedOrigin !== '*') {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function allowedOrigin(string $origin): ?string
    {
        $configured = trim($this->allowedOrigins);
        if ($configured === '') {
            // No CORS origins configured — block all cross-origin requests
            return null;
        }

        if ($configured === '*') {
            // Wildcard — allowed but WITHOUT credentials
            return '*';
        }

        if ($origin === '') {
            return null;
        }

        $allowed = array_map('trim', explode(',', $configured));

        return in_array($origin, $allowed, true) ? $origin : null;
    }
}
