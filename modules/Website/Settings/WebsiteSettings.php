<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class WebsiteSettings extends ModuleSettings
{
    public const COVERAGE_DIRECT_RADIUS_KM = 'coverage_direct_radius_km';
    public const COVERAGE_EXTENDED_RADIUS_KM = 'coverage_extended_radius_km';
    public const COVERAGE_POP_LOCATIONS = 'coverage_pop_locations';
    public const COVERAGE_AVAILABLE_PLANS = 'coverage_available_plans';
    public const COVERAGE_WAITLIST_PLAN_INTEREST = 'coverage_waitlist_plan_interest';

    protected function moduleName(): string
    {
        return 'website';
    }

    public function siteName(): string
    {
        return $this->getString('site_name');
    }

    public function maintenanceMode(): bool
    {
        return $this->getBool('maintenance_mode');
    }

    public function contactEmail(): string
    {
        return $this->getString('contact_email');
    }

    public function supportPhone(): string
    {
        return $this->getString('support_phone');
    }

    public function directCoverageRadiusKm(): float
    {
        return max(1.0, $this->getFloat(self::COVERAGE_DIRECT_RADIUS_KM));
    }

    public function extendedCoverageRadiusKm(): float
    {
        return max($this->directCoverageRadiusKm(), $this->getFloat(self::COVERAGE_EXTENDED_RADIUS_KM));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coveragePopLocations(): array
    {
        $locations = $this->getJson(self::COVERAGE_POP_LOCATIONS);

        return array_values(array_filter(
            $locations,
            static fn(mixed $location): bool => is_array($location)
                && isset($location['latitude'], $location['longitude'])
        ));
    }

    /**
     * @return list<string>
     */
    public function coverageAvailablePlans(): array
    {
        $plans = $this->getJson(self::COVERAGE_AVAILABLE_PLANS);

        return array_values(array_filter($plans, static fn(mixed $plan): bool => is_string($plan) && $plan !== ''));
    }

    public function coverageWaitlistPlanInterest(): string
    {
        return $this->getString(self::COVERAGE_WAITLIST_PLAN_INTEREST);
    }
}
