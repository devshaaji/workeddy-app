<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Authorization;

interface IAuthorizationService
{
    public function authorize(string $permission, ?string $tenantId = null): void;
}
