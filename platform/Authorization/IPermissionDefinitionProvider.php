<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Authorization;

interface IPermissionDefinitionProvider
{
    /**
     * @return list<PermissionDefinition>
     */
    public function definitions(): array;
}
