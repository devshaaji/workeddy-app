<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class PaymentSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'payment';
    }

    /** @return SettingDefinition[] */
    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: PaymentSettings::DEFAULT_GATEWAY,
                module: 'payment',
                type: SettingType::STRING,
                default: 'paystack',
                label: 'Default Gateway',
                description: 'Default payment gateway used when checkout requests do not specify one.',
            ),
            new SettingDefinition(
                key: PaymentSettings::GATEWAYS,
                module: 'payment',
                type: SettingType::JSON,
                default: [
                    'paystack' => [
                        'enabled' => true,
                        'driver' => 'paystack',
                        'label' => 'Paystack',
                        'public_key' => '',
                        'secret_key' => '',
                        'base_url' => 'https://api.paystack.co',
                        'webhook_secret' => '',
                    ],
                    'flutterwave' => [
                        'enabled' => false,
                        'driver' => 'flutterwave',
                        'label' => 'Flutterwave',
                        'public_key' => '',
                        'secret_key' => '',
                        'base_url' => 'https://api.flutterwave.com',
                    ],
                    'stripe' => [
                        'enabled' => false,
                        'driver' => 'stripe',
                        'label' => 'Stripe',
                        'public_key' => '',
                        'secret_key' => '',
                        'base_url' => 'https://api.stripe.com',
                    ],
                ],
                label: 'Payment Gateways',
                description: 'Structured gateway configuration keyed by gateway name.',
            ),
            new SettingDefinition(
                key: PaymentSettings::DEFAULT_CURRENCY,
                module: 'payment',
                type: SettingType::STRING,
                default: 'USD',
                label: 'Default Currency',
                description: 'Default currency for payments.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'payment',
            label: 'Payment',
            viewPermissions: [\WorkEddy\Modules\Payment\Authorization\PaymentPermissions::RECORD_PAYMENT],
            editPermissions: [\WorkEddy\Modules\Payment\Authorization\PaymentPermissions::RECORD_PAYMENT],
            sortOrder: 210,
        );
    }
}
