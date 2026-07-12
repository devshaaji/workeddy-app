<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class ReportingSettings extends ModuleSettings
{
    public const DEFAULT_REVENUE_WINDOW_DAYS = 'default_revenue_window_days';
    public const INCLUDE_EXPIRED_CUSTOMERS = 'include_expired_customers';
    public const TEMPLATE_VERSION = 'template_version';
    public const METHODOLOGY_NOTE = 'methodology_note';
    public const LIMITATIONS_NOTE = 'limitations_note';
    public const PRIVACY_NOTE = 'privacy_note';
    public const DOWNLOAD_LINK_TTL_MINUTES = 'download_link_ttl_minutes';
    public const IMPACT_INJURY_PREVENTION_RATE = 'impact_injury_prevention_rate';
    public const IMPACT_LOST_WORKDAYS_PER_INJURY = 'impact_lost_workdays_per_injury';
    public const IMPACT_COST_PER_LOST_WORKDAY = 'impact_cost_per_lost_workday';
    public const IMPACT_ESTIMATE_DISCLAIMER = 'impact_estimate_disclaimer';

    protected function moduleName(): string
    {
        return 'reporting';
    }

    public function defaultRevenueWindowDays(): int
    {
        return $this->getInt(self::DEFAULT_REVENUE_WINDOW_DAYS);
    }

    public function includeExpiredCustomers(): bool
    {
        return $this->getBool(self::INCLUDE_EXPIRED_CUSTOMERS);
    }

    public function templateVersion(): string
    {
        return $this->getString(self::TEMPLATE_VERSION);
    }

    public function methodologyNote(): string
    {
        return $this->getString(self::METHODOLOGY_NOTE);
    }

    public function limitationsNote(): string
    {
        return $this->getString(self::LIMITATIONS_NOTE);
    }

    public function privacyNote(): string
    {
        return $this->getString(self::PRIVACY_NOTE);
    }

    public function downloadLinkTtlMinutes(): int
    {
        return $this->getInt(self::DOWNLOAD_LINK_TTL_MINUTES);
    }

    public function impactInjuryPreventionRate(): float
    {
        return $this->getFloat(self::IMPACT_INJURY_PREVENTION_RATE);
    }

    public function impactLostWorkdaysPerInjury(): float
    {
        return $this->getFloat(self::IMPACT_LOST_WORKDAYS_PER_INJURY);
    }

    public function impactCostPerLostWorkday(): float
    {
        return $this->getFloat(self::IMPACT_COST_PER_LOST_WORKDAY);
    }

    public function impactEstimateDisclaimer(): string
    {
        return $this->getString(self::IMPACT_ESTIMATE_DISCLAIMER);
    }
}
