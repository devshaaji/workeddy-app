<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Ergonomics\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class ErgonomicsPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'ergonomics';
    }

    public function definitions(): array
    {
        return [
            new PermissionDefinition(ErgonomicsPermissions::SCORE, 'Score ergonomic assessments', 'Run REBA, RULA, and NIOSH scoring engines.', 'ergonomics', 'write', 'high'),
            new PermissionDefinition(ErgonomicsPermissions::VIEW_MODELS, 'View ergonomic scoring models', 'View available ergonomic scoring models and input fields.', 'ergonomics', 'read', 'medium'),
        ];
    }
}
