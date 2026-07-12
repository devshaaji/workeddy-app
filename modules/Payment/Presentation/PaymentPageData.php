<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Presentation;

use WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsService;

final class PaymentPageData
{
    public function __construct(
        private readonly IPaymentRecordRepository $payments,
        private readonly SettingsService $settings,
    ) {}

    public function common(UserContext $ctx): array
    {
        return [
            'user' => [
                'id' => $ctx->userId,
                'email' => $ctx->email,
                'permissions' => $ctx->permissions,
            ],
            'moduleSettings' => [
                'defaultGateway' => $this->settings->get('payment.default_gateway'),
                'gateways' => $this->settings->get('payment.gateways'),
                'defaultCurrency' => $this->settings->get('payment.default_currency'),
            ]
        ];
    }

    public function index(UserContext $ctx): array
    {
        // Simple mock of getting payments
        $recentPayments = $this->payments->list();

        return [
            'pageTitle' => 'Payments',
            'payments' => array_map(fn($p) => $p->toArray(), $recentPayments)
        ];
    }
}
