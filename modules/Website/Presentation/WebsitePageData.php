<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Presentation;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Website\Settings\WebsiteSettings;

final class WebsitePageData
{
    public function __construct(
        private readonly WebsiteSettings $settings,
        private readonly ISubscriptionPlanRepository $subscriptionPlanRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function common(): array
    {
        return [
            'site_name' => $this->settings->siteName(),
            'contact_email' => $this->settings->contactEmail(),
            'support_phone' => $this->settings->supportPhone(),
            'maintenance_mode' => $this->settings->maintenanceMode(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        $plans = $this->marketingPlans();

        return [
            'featured_plans' => array_slice($plans, 0, 3),
            'plans_total' => count($plans),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function plans(): array
    {
        $plans = $this->marketingPlans();

        return [
            'plans' => $plans,
            'plans_total' => count($plans),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marketingPlans(): array
    {
        $plans = array_map(
            fn($plan): array => $this->mapPlan($plan->toArray()),
            $this->subscriptionPlanRepository->listActive(),
        );

        if ($plans !== []) {
            usort($plans, fn(array $left, array $right): int => $this->planSortWeight($left) <=> $this->planSortWeight($right));

            return $plans;
        }

        return [
            $this->fallbackPlan('free', 'Free', 0.00, 'USD', 'monthly', 'Best for early pilots and smaller teams', false, [
                '10 completed assessments each month',
                'Manual and video assessments',
                '1 organization',
                'Core reporting',
            ], 'Best for early pilots and smaller teams', 10),
            $this->fallbackPlan('professional', 'Professional', 99.00, 'USD', 'monthly', 'Best for growing safety and operations teams', true, [
                '500 completed assessments each month',
                'Unlimited team members',
                'Exportable reports',
                'Dashboard visibility',
                'Prioritized intervention insights',
            ], 'Best for growing safety and operations teams', 20),
            $this->fallbackPlan('enterprise', 'Enterprise', 0.00, 'USD', 'annual', 'Best for multi-site and large scale programs', false, [
                'Custom usage limits',
                'Advanced admin controls',
                'Expanded analytics',
                'Onboarding and procurement support',
            ], 'Best for multi-site and large scale programs', 30),
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function mapPlan(array $plan): array
    {
        $code = strtolower((string) ($plan['code'] ?? ''));
        $name = (string) ($plan['name'] ?? 'Plan');
        $features = is_array($plan['features'] ?? null) ? $plan['features'] : [];

        $badge = match (true) {
            str_contains($code, 'free') || str_contains($code, 'starter') => 'Best for early pilots and smaller teams',
            str_contains($code, 'pro') || str_contains($code, 'professional') => 'Best for growing safety and operations teams',
            str_contains($code, 'enterprise') => 'Best for multi-site and large scale programs',
            default => 'Ergonomics Plan',
        };

        $featureList = $this->humanizeFeatures($features);
        if ($featureList === []) {
            $featureList = [
                'Ergonomic Risk Assessments',
                'REBA, RULA, and NIOSH methods',
                'Business-grade reporting',
            ];
        }

        return [
            'code' => $code !== '' ? $code : strtolower(str_replace(' ', '-', $name)),
            'name' => $name,
            'description' => $plan['description'] ?? null,
            'price' => (float) ($plan['price'] ?? 0.0),
            'currency' => (string) ($plan['currency'] ?? 'USD'),
            'billing_cycle' => (string) ($plan['billing_cycle'] ?? 'monthly'),
            'display_order' => isset($plan['display_order']) ? (int) $plan['display_order'] : null,
            'is_active' => (bool) ($plan['is_active'] ?? true),
            'badge' => $badge,
            'summary' => $badge,
            'features' => $featureList,
            'is_featured' => str_contains($code, 'pro') || str_contains($code, 'professional'),
            'cta_label' => str_contains($code, 'enterprise') ? 'Contact Sales' : (str_contains($code, 'pro') || str_contains($code, 'professional') ? 'Start Professional Trial' : 'Start Free'),
        ];
    }

    /**
     * Turns the plan's raw feature/limit map (e.g. max_worksites: 5,
     * has_export_access: true) into short marketing bullet lines.
     *
     * @param array<string, mixed> $features
     * @return list<string>
     */
    private function humanizeFeatures(array $features): array
    {
        $lines = [];
        foreach ($features as $key => $value) {
            $label = ucfirst(str_replace('_', ' ', (string) $key));
            if (is_bool($value)) {
                if ($value) {
                    $lines[] = $label;
                }
                continue;
            }
            if (is_numeric($value)) {
                $lines[] = sprintf('%s: %s', $label, (string) $value);
                continue;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $features
     * @return array<string, mixed>
     */
    private function fallbackPlan(
        string $code,
        string $name,
        float $price,
        string $currency,
        string $billingCycle,
        string $badge,
        bool $isFeatured,
        array $features,
        string $summary = '',
        ?int $displayOrder = null,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'description' => null,
            'price' => $price,
            'currency' => $currency,
            'billing_cycle' => $billingCycle,
            'display_order' => $displayOrder,
            'is_active' => true,
            'badge' => $badge,
            'summary' => $summary !== '' ? $summary : $badge,
            'features' => $features,
            'is_featured' => $isFeatured,
            'cta_label' => $code === 'enterprise' ? 'Contact Sales' : ($code === 'professional' ? 'Start Professional Trial' : 'Start Free'),
        ];
    }

    /**
     * Determines home/plans-page display order. Plans define their own
     * order via `display_order` (admin-controlled, set on the
     * SubscriptionPlan record) so the Website module never has to guess
     * tier position from the plan's code or name. Falls back to a
     * free/pro/enterprise heuristic only for plans that haven't set an
     * explicit display_order yet, and finally to price ascending.
     *
     * @param array<string, mixed> $plan
     */
    private function planSortWeight(array $plan): int
    {
        if (isset($plan['display_order']) && $plan['display_order'] !== null) {
            return (int) $plan['display_order'];
        }

        $code = strtolower((string) ($plan['code'] ?? ''));

        return match (true) {
            str_contains($code, 'free') || str_contains($code, 'starter') => 10,
            str_contains($code, 'pro') || str_contains($code, 'professional') => 20,
            str_contains($code, 'enterprise') => 30,
            default => 1000 + (int) round((float) ($plan['price'] ?? 0.0)),
        };
    }
}
