<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http\Middleware;

use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;

final class CsrfMiddleware implements IMiddleware
{
    public const SESSION_KEY = '_csrf_token';

    public function process(Request $request, callable $next): Response
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $expected = (string) ($_SESSION[self::SESSION_KEY] ?? '');
        $provided = (string) (
            $request->header('x-csrf-token')
            ?? $request->input('_token')
            ?? ''
        );

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return Response::error('Invalid CSRF token', 419);
        }

        return $next($request);
    }
}
