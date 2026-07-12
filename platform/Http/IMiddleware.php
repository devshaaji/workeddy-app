<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

interface IMiddleware
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
