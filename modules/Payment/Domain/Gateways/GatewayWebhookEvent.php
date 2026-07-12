<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Gateways;

use WorkEddy\Modules\Payment\Domain\Entities\PaymentStatus;

final class GatewayWebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $transactionId,
        public readonly ?string $gatewayReference,
        public readonly PaymentStatus $status,
        public readonly ?string $notes = null,
        public readonly array $payload = [],
    ) {}
}
