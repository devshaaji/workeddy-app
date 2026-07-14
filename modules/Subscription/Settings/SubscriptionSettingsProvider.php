<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class SubscriptionSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'subscription';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'default_currency',
                module: 'subscription',
                type: SettingType::STRING,
                default: 'USD',
                label: 'Default Currency',
                description: 'Default currency used when a plan does not specify one.',
            ),
            new SettingDefinition(
                key: 'default_billing_cycle',
                module: 'subscription',
                type: SettingType::STRING,
                default: 'monthly',
                label: 'Default Billing Cycle',
                description: 'Default billing cycle ("monthly" or "annual") applied when activating a subscription without an explicit cycle.',
            ),
            new SettingDefinition(
                key: 'trial_days',
                module: 'subscription',
                type: SettingType::INTEGER,
                default: 14,
                label: 'Trial Days',
                description: 'Number of trial days offered before the first billing cycle begins.',
            ),
            new SettingDefinition(
                key: 'grace_period_days',
                module: 'subscription',
                type: SettingType::INTEGER,
                default: 3,
                label: 'Grace Period Days',
                description: 'Days after an unpaid invoice before an active subscription is auto-suspended.',
            ),
            new SettingDefinition(
                key: 'auto_suspend_on_expiry',
                module: 'subscription',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Auto Suspend On Expiry',
                description: 'Automatically suspend subscriptions once their expiry date passes without renewal.',
            ),
            new SettingDefinition(
                key: 'allow_self_service_upgrade',
                module: 'subscription',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Allow Self-Service Upgrade',
                description: 'Allow organization admins to change their own subscription plan without support intervention.',
            ),
            new SettingDefinition(
                key: 'auto_provision_on_signup',
                module: 'subscription',
                type: SettingType::BOOLEAN,
                default: false,
                label: 'Auto-Provision On Signup',
                description: 'Automatically activate a subscription for every newly created Organization using the Default Plan Code below. Leave off for sales-assisted onboarding.',
            ),
            new SettingDefinition(
                key: 'default_plan_code',
                module: 'subscription',
                type: SettingType::STRING,
                default: '',
                label: 'Default Plan Code',
                description: 'Plan code granted automatically on signup when Auto-Provision On Signup is enabled. Must match an existing active plan\'s code.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'subscription',
            label: 'Subscription',
            viewPermissions: [\WorkEddy\Modules\Subscription\Authorization\SubscriptionPermissions::MANAGE_PLANS],
            editPermissions: [\WorkEddy\Modules\Subscription\Authorization\SubscriptionPermissions::MANAGE_PLANS],
            sortOrder: 160,
        );
    }
}
