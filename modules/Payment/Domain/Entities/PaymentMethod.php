<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Entities;

enum PaymentMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_CARD = 'credit_card';
    case CASH = 'cash';
    case ONLINE_GATEWAY = 'online_gateway';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CREDIT_CARD => 'Credit Card',
            self::CASH => 'Cash',
            self::ONLINE_GATEWAY => 'Online Gateway',
        };
    }
}
