<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Authorization;

final class PaymentPermissions
{
    public const VIEW_PAYMENTS = 'payment.view_payments';
    public const RECORD_PAYMENT = 'payment.record_payment';
    public const REFUND_PAYMENT = 'payment.refund_payment';
}
