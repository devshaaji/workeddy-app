<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class PaymentPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function definitions(): array
    {
        return [
            new PermissionDefinition('payment', PaymentPermissions::VIEW_PAYMENTS, 'View Payments', 'Can view payment records and history', 'read', defaultAssignments: ['manager', 'supervisor', 'finance', 'support']),
            new PermissionDefinition('payment', PaymentPermissions::RECORD_PAYMENT, 'Record Payment', 'Can record manual payments against invoices', 'write', defaultAssignments: ['manager', 'finance']),
            new PermissionDefinition('payment', PaymentPermissions::REFUND_PAYMENT, 'Refund Payment', 'Can issue refunds for payments', 'write', risk: 'high', defaultAssignments: ['manager', 'finance']),
        ];
    }
}
