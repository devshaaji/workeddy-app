<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Infrastructure\Gateways;

use WorkEddy\Modules\Payment\Domain\Gateways\GatewayCheckoutRequest;
use WorkEddy\Modules\Payment\Domain\Gateways\GatewayCheckoutResponse;
use WorkEddy\Modules\Payment\Domain\Gateways\GatewayWebhookEvent;
use WorkEddy\Modules\Payment\Domain\Gateways\PaymentGatewayInterface;
use WorkEddy\Shared\Exceptions\ValidationException;

final class UnsupportedPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $name,
        private readonly bool $enabled,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function initializeCheckout(GatewayCheckoutRequest $request): GatewayCheckoutResponse
    {
        throw new ValidationException(['gateway' => 'Payment gateway driver is not implemented.']);
    }

    public function parseWebhook(string $rawPayload, array $headers): GatewayWebhookEvent
    {
        throw new ValidationException(['gateway' => 'Payment gateway driver is not implemented.']);
    }

    public function verifyTransaction(string $transactionId): GatewayWebhookEvent
    {
        throw new ValidationException(['gateway' => 'Payment gateway driver is not implemented.']);
    }
}
