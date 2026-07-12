<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class PaymentSettings extends ModuleSettings
{
    public const DEFAULT_GATEWAY = 'default_gateway';
    public const GATEWAYS = 'gateways';
    public const DEFAULT_CURRENCY = 'default_currency';

    protected function moduleName(): string
    {
        return 'payment';
    }

    public function defaultGateway(): string
    {
        return $this->getString(self::DEFAULT_GATEWAY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function gateways(): array
    {
        $gateways = $this->getJson(self::GATEWAYS);

        return array_filter($gateways, static fn(mixed $config): bool => is_array($config));
    }

    /**
     * @return array<string, mixed>
     */
    public function gatewayConfig(string $gateway): array
    {
        $gateways = $this->gateways();
        $config = $gateways[$gateway] ?? [];

        return is_array($config) ? $config : [];
    }

    public function gatewayEnabled(string $gateway): bool
    {
        return (bool) ($this->gatewayConfig($gateway)['enabled'] ?? false);
    }

    public function gatewayDriver(string $gateway): string
    {
        $config = $this->gatewayConfig($gateway);

        return (string) ($config['driver'] ?? $gateway);
    }

    public function defaultCurrency(): string
    {
        return $this->getString(self::DEFAULT_CURRENCY);
    }
}
