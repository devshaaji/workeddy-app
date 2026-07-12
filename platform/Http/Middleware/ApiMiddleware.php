<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http\Middleware;

use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;

final class ApiMiddleware implements IMiddleware
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request);
    }
}
