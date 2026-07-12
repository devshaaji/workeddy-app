<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Gateways;

final class GatewayCheckoutRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $customerEmail,
        public readonly ?string $callbackUrl = null,
        public readonly array $metadata = [],
    ) {}
}
