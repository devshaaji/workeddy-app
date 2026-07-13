<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

use WorkEddy\Platform\Settings\SettingsValidationException;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\MethodNotAllowedException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ServiceUnavailableException;
use WorkEddy\Shared\Exceptions\TooManyRequestsException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Exceptions\WrongScopeException;
use WorkEddy\Shared\Presentation\ViewRenderer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ExceptionHandler
{
    public function __construct(
        private readonly bool $debug = false,
        private readonly ViewRenderer $views,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function handle(\Throwable $e, ?Request $request = null): Response
    {
        $status = $this->statusFor($e);
        try {
            $this->log($e, $request, $status);
        } catch (\Throwable) {
            // Logging is best-effort; error rendering must still complete.
        }

        if ($e instanceof ValidationException) {
            return Response::error($e->getMessage(), 400, $e->getErrors());
        }

        if ($e instanceof \InvalidArgumentException) {
            return Response::error($e->getMessage(), 400);
        }

        if ($e instanceof SettingsValidationException) {
            return Response::error($e->getMessage(), 422);
        }


        // Settings validation → 422
        if ($e instanceof SettingsValidationException) {
            return Response::error($e->getMessage(), 422);
        }

        // Authentication → 401
        if ($e instanceof AuthenticationException) {
            return Response::error($e->getMessage(), 401);
        }

        // Forbidden → 403
        if ($e instanceof WrongScopeException) {
            if ($request !== null && !$this->expectsJson($request)) {
                return $this->views->renderScopeErrorPage(
                    $e->getMessage(),
                    $e->organizationName(),
                    $e->organizationUuid(),
                    $e->suggestedAction(),
                );
            }

            return Response::json([
                'code' => 'WRONG_SCOPE',
                'message' => $e->getMessage(),
                'data' => [
                    'organizationName' => $e->organizationName(),
                    'organizationUuid' => $e->organizationUuid(),
                    'suggestedAction' => $e->suggestedAction(),
                    'redirectTo' => $this->scopeErrorUrl($e),
                ],
            ], 403);
        }

        if ($e instanceof ForbiddenException) {
            if ($request !== null && !$this->expectsJson($request)) {
                return $this->views->renderErrorPage(403, 'Access denied');
            }

            return Response::error($e->getMessage(), 403);
        }

        // Not found → 404
        if ($e instanceof NotFoundException) {
            if ($request !== null && !$this->expectsJson($request)) {
                return $this->views->renderErrorPage(404, 'Page not found');
            }

            return Response::error($e->getMessage(), 404);
        }

        // Conflict → 409
        if ($e instanceof ConflictException) {
            return Response::error($e->getMessage(), 409);
        }

        if ($e instanceof TooManyRequestsException) {
            return Response::error($e->getMessage(), 429);
        }

        if ($status < 500) {
            return Response::error($e->getMessage(), $status);
        }

        // Internal error → 500
        if ($request !== null && !$this->expectsJson($request)) {
            return $this->views->renderErrorPage(status: 500, message: $e->getMessage());
        }
        return Response::error(
            $this->debug ? $e->getMessage() : 'An internal error occurred. Please try again later.',
            500,
        );
    }

    private function expectsJson(Request $request): bool
    {
        $uri = $request->getUri();
        if ($uri === '/api' || str_starts_with($uri, '/api/')) {
            return true;
        }

        $requestedWith = strtolower((string) $request->header('X-Requested-With', ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        return str_contains($contentType, 'json');
    }

    private function scopeErrorUrl(WrongScopeException $e): string
    {
        $query = array_filter([
            'message' => $e->getMessage(),
            'organization' => $e->organizationName(),
            'organization_uuid' => $e->organizationUuid(),
            'action' => $e->suggestedAction(),
        ], static fn($value): bool => is_string($value) && trim($value) !== '');

        $qs = http_build_query($query);

        return '/scope-error' . ($qs !== '' ? '?' . $qs : '');
    }

    private function log(\Throwable $e, ?Request $request, int $status): void
    {
        $context = [
            'exception' => $e,
            'status' => $status,
            'method' => $request?->method,
            'uri' => $request?->uri,
            'client_ip' => $request?->getClientIp(),
            'request_id' => $request?->header('x-request-id'),
        ];

        if ($status >= 500) {
            $this->logger->error($e->getMessage(), $context);
            return;
        }

        if ($status >= 400) {
            $this->logger->warning($e->getMessage(), $context);
        }
    }

    private function statusFor(\Throwable $e): int
    {
        if ($e instanceof ValidationException || $e instanceof \InvalidArgumentException) {
            return 400;
        }
        if ($e instanceof SettingsValidationException) {
            return 422;
        }
        if ($e instanceof AuthenticationException) {
            return 401;
        }
        if ($e instanceof ForbiddenException) {
            return 403;
        }
        if ($e instanceof NotFoundException) {
            return 404;
        }
        if ($e instanceof MethodNotAllowedException) {
            return 405;
        }
        if ($e instanceof ConflictException) {
            return 409;
        }
        if ($e instanceof TooManyRequestsException) {
            return 429;
        }
        if ($e instanceof ServiceUnavailableException) {
            return 503;
        }

        return 500;
    }
}
