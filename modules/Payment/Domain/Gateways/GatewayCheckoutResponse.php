<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Gateways;

final class GatewayCheckoutResponse
{
    /**
     * @param array<string, mixed> $modal
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $gatewayReference,
        public readonly array $modal,
        public readonly array $payload = [],
    ) {}
}
