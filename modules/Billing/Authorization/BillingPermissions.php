<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Authorization;

final class BillingPermissions
{
    public const MANAGE_QUOTATIONS = 'billing.manage_quotations';
    public const MANAGE_INVOICES = 'billing.manage_invoices';
    public const VIEW_BILLING = 'billing.view_billing';
}
