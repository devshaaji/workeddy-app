<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Gateways;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function isEnabled(): bool;

    public function initializeCheckout(GatewayCheckoutRequest $request): GatewayCheckoutResponse;

    /**
     * @param array<string, string> $headers
     */
    public function parseWebhook(string $rawPayload, array $headers): GatewayWebhookEvent;

    public function verifyTransaction(string $transactionId): GatewayWebhookEvent;
}
