<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Gateways;

use WorkEddy\Shared\Exceptions\ValidationException;

final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    /**
     * @param iterable<PaymentGatewayInterface> $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->name()] = $gateway;
        }
    }

    public function get(string $gateway): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$gateway])) {
            throw new ValidationException(['gateway' => 'Payment gateway is not configured.']);
        }

        if (!$this->gateways[$gateway]->isEnabled()) {
            throw new ValidationException(['gateway' => 'Payment gateway is disabled.']);
        }

        return $this->gateways[$gateway];
    }
}
