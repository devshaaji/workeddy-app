<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;

final class AuthGuardMiddleware implements IMiddleware
{
    public function __construct(
        private readonly ISessionService $session
    ) {}

    public function process(Request $request, callable $next): Response
    {
        if ($this->session->getUserContext() === null) {
            if ($request->expectsJson()) {
                return Response::json(['error' => 'Unauthenticated'], 401);
            }
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
