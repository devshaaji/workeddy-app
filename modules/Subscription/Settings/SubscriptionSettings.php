<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class SubscriptionSettings extends ModuleSettings
{
    public const DEFAULT_CURRENCY = 'default_currency';
    public const DEFAULT_BILLING_CYCLE = 'default_billing_cycle';
    public const TRIAL_DAYS = 'trial_days';
    public const GRACE_PERIOD_DAYS = 'grace_period_days';
    public const AUTO_SUSPEND_ON_EXPIRY = 'auto_suspend_on_expiry';
    public const ALLOW_SELF_SERVICE_UPGRADE = 'allow_self_service_upgrade';
    public const AUTO_PROVISION_ON_SIGNUP = 'auto_provision_on_signup';
    public const DEFAULT_PLAN_CODE = 'default_plan_code';

    protected function moduleName(): string
    {
        return 'subscription';
    }

    public function defaultCurrency(): string
    {
        return $this->getString(self::DEFAULT_CURRENCY);
    }

    public function defaultBillingCycle(): string
    {
        return $this->getString(self::DEFAULT_BILLING_CYCLE);
    }

    public function trialDays(): int
    {
        return $this->getInt(self::TRIAL_DAYS);
    }

    public function gracePeriodDays(): int
    {
        return $this->getInt(self::GRACE_PERIOD_DAYS);
    }

    public function autoSuspendOnExpiry(): bool
    {
        return $this->getBool(self::AUTO_SUSPEND_ON_EXPIRY);
    }

    public function allowSelfServiceUpgrade(): bool
    {
        return $this->getBool(self::ALLOW_SELF_SERVICE_UPGRADE);
    }

    /**
     * Whether a new Organization should automatically receive a
     * subscription on the `organization.created` event. Deployments doing
     * manual/sales-assisted onboarding can disable this.
     */
    public function autoProvisionOnSignup(): bool
    {
        return $this->getBool(self::AUTO_PROVISION_ON_SIGNUP);
    }

    /**
     * Plan code granted automatically on signup when auto-provisioning is
     * enabled. Empty means "don't auto-provision" even if the flag above
     * is on (defensive default so a missing setting can't silently start
     * granting a paid tier for free).
     */
    public function defaultPlanCode(): string
    {
        return $this->getString(self::DEFAULT_PLAN_CODE);
    }
}
