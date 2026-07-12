<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class BillingPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function definitions(): array
    {
        return [
            new PermissionDefinition('billing', BillingPermissions::MANAGE_QUOTATIONS, 'Manage Quotations', 'Can create, edit, and send quotations', 'write', defaultAssignments: ['manager', 'supervisor', 'sales']),
            new PermissionDefinition('billing', BillingPermissions::MANAGE_INVOICES, 'Manage Invoices', 'Can create, edit, and void invoices', 'write', risk: 'high', defaultAssignments: ['manager', 'finance']),
            new PermissionDefinition('billing', BillingPermissions::VIEW_BILLING, 'View Billing Records', 'Can view quotations and invoices', 'read', defaultAssignments: ['manager', 'supervisor', 'sales', 'finance', 'support']),
        ];
    }
}
