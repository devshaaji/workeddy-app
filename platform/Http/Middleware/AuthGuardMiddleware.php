<?php

/**
 * Authentication guard middleware — infrastructure hook.
 *
 * Checks that the request has an authenticated session.
 * Does NOT implement authentication logic itself — that belongs to IAM.
 * Just verifies that ISessionService reports authenticated.
 *
 * Routes that should be public (e.g., /api/auth/login) are excluded.
 */

declare(strict_types=1);

namespace WorkEddy\Platform\Http\Middleware;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\SessionSecurityService;
use WorkEddy\Shared\Exceptions\ForbiddenException;

final class AuthGuardMiddleware implements IMiddleware
{
    /** @var string[] URI prefixes that bypass authentication. */
    private array $publicPaths;

    /**
     * @param ISessionService $session Session service (injected by DI).
     * @param string[] $publicPaths URI prefixes that bypass the guard.
     */
    public function __construct(
        private readonly ISessionService $session,
        array $publicPaths = [],
        private readonly ?IUserRepository $users = null,
        private readonly ?SessionSecurityService $sessionSecurity = null,
    ) {
        $this->publicPaths = $publicPaths;
    }

    public function process(Request $request, callable $next): Response
    {
        // Skip guard for public paths
        foreach ($this->publicPaths as $prefix) {
            if (str_starts_with($request->getUri(), $prefix)) {
                return $next($request);
            }
        }

        if ($this->session->getUserContext() === null) {
            return $this->authenticationFailureResponse($request);
        }

        if ($this->sessionSecurity !== null && !$this->sessionSecurity->enforce($request)) {
            return $this->authenticationFailureResponse($request, 'Session expired');
        }

        if (!$this->authenticatedAccountIsActive()) {
            $this->session->destroy();
            throw new ForbiddenException('Account is not active');
        }

        if (!$this->authorizationContextIsFresh()) {
            $this->session->destroy();
            return $this->authenticationFailureResponse($request, 'Authorization context is stale. Please sign in again.');
        }

        return $next($request);
    }

    private function authenticationFailureResponse(Request $request, string $message = 'Authentication required'): Response
    {
        $uri = $request->getUri();
        $isApiRequest = str_starts_with($uri, '/api/') || str_starts_with($uri, '/api/v1/');

        if ($request->getMethod() === 'GET' && !$isApiRequest) {
            return Response::redirect('/login');
        }

        return Response::error($message, 401);
    }

    private function authenticatedAccountIsActive(): bool
    {
        if ($this->users === null) {
            return true;
        }

        $ctx = $this->session->getUserContext();
        $userId = $ctx?->userId;
        if ($userId === null) {
            return false;
        }

        $user = $this->users->findById($userId);

        return $user !== null && $user->isActive();
    }

    private function authorizationContextIsFresh(): bool
    {
        if ($this->users === null) {
            return true;
        }

        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            return true;
        }

        $user = $this->users->findById($ctx->userId);
        if ($user === null) {
            return false;
        }

        return $user->getAuthzVersion() === $ctx->authzVersion;
    }
}
