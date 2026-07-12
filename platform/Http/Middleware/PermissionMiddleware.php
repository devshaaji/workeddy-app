<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http\Middleware;

use WorkEddy\Platform\Authorization\IAuthorizationService;
use WorkEddy\Platform\Http\IMiddleware;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;

final class PermissionMiddleware implements IMiddleware
{
    public function __construct(
        private readonly IAuthorizationService $authorization,
        private readonly string $permission,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $this->authorization->authorize($this->permission, $this->tenantScope($request));

        return $next($request);
    }

    private function tenantScope(Request $request): ?string
    {
        $tenantId = trim((string) ($request->routeParam('tenant_id') ?? ''));

        return $tenantId === '' ? null : $tenantId;
    }
}
