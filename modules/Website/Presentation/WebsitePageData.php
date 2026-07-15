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
            $this->fallbackPlan(
                'free',
                'Pilot',
                'For organizations evaluating WorkEddy with a limited number of authorized tasks and users.',
                0.00,
                'USD',
                'monthly',
                [
                    'Guided pilot onboarding',
                    'Structured ergonomic assessments',
                    'Worker feedback intake',
                    'Basic reporting exports',
                ],
                false,
                'Request Pilot',
                '/register',
                10,
            ),
            $this->fallbackPlan(
                'professional',
                'Professional',
                'For organizations managing ongoing ergonomic assessment and corrective-action workflows.',
                299.00,
                'USD',
                'monthly',
                [
                    'Ongoing assessment workflows',
                    'Corrective action tracking',
                    'Before-and-after reassessment',
                    'Dashboard reporting and filtering',
                ],
                true,
                'Start Professional Trial',
                '/register',
                20,
            ),
            $this->fallbackPlan(
                'enterprise',
                'Multi-site',
                'For organizations requiring additional worksites, governance controls, reporting, and implementation support.',
                999.00,
                'USD',
                'monthly',
                [
                    'Additional worksites',
                    'Governance and role controls',
                    'Consolidated reporting',
                    'Implementation support',
                ],
                false,
                'Contact Sales',
                '/contact-us',
                30,
                true,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function mapPlan(array $plan): array
    {
        $code = strtolower((string) ($plan['code'] ?? ''));
        $features = is_array($plan['features'] ?? null) ? $plan['features'] : [];
        $marketing = $this->marketingMetadataForPlan($code, $features);

        return [
            'code' => $code !== '' ? $code : strtolower(str_replace(' ', '-', (string) ($plan['name'] ?? 'plan'))),
            'name' => (string) ($plan['name'] ?? 'Plan'),
            'description' => $plan['description'] ?? null,
            'price' => (float) ($plan['price'] ?? 0.0),
            'currency' => (string) ($plan['currency'] ?? 'USD'),
            'billing_cycle' => (string) ($plan['billing_cycle'] ?? 'monthly'),
            'display_order' => isset($plan['display_order']) ? (int) $plan['display_order'] : null,
            'is_active' => (bool) ($plan['is_active'] ?? true),
            'summary' => $marketing['summary'],
            'features' => $marketing['highlights'],
            'is_featured' => $marketing['featured'],
            'cta_label' => $marketing['cta_label'],
            'cta_href' => $marketing['cta_href'],
            'is_custom_pricing' => $marketing['custom_pricing'],
        ];
    }

    /**
     * @param array<string, mixed> $features
     * @return array{summary:string,highlights:list<string>,featured:bool,cta_label:string,cta_href:string,custom_pricing:bool}
     */
    private function marketingMetadataForPlan(string $code, array $features): array
    {
        $marketing = is_array($features['marketing'] ?? null) ? $features['marketing'] : [];

        $defaultSummary = match (true) {
            $code === 'free' => 'For organizations evaluating WorkEddy with a limited number of authorized tasks and users.',
            $code === 'professional' => 'For organizations managing ongoing ergonomic assessment and corrective-action workflows.',
            $code === 'enterprise' => 'For organizations requiring additional worksites, governance controls, reporting, and implementation support.',
            default => 'WorkEddy plan',
        };

        $defaultHighlights = match (true) {
            $code === 'free' => [
                'Guided pilot onboarding',
                'Structured ergonomic assessments',
                'Worker feedback intake',
                'Basic reporting exports',
            ],
            $code === 'professional' => [
                'Ongoing assessment workflows',
                'Corrective action tracking',
                'Before-and-after reassessment',
                'Dashboard reporting and filtering',
            ],
            $code === 'enterprise' => [
                'Additional worksites',
                'Governance and role controls',
                'Consolidated reporting',
                'Implementation support',
            ],
            default => [
                'Ergonomic risk assessment workflows',
                'Recognized assessment methods',
                'Operational reporting',
            ],
        };

        $defaultCtaLabel = match (true) {
            $code === 'enterprise' => 'Contact Sales',
            $code === 'professional' => 'Start Professional Trial',
            default => 'Request Pilot',
        };

        $defaultCtaHref = $code === 'enterprise' ? '/contact-us' : '/register';

        $summary = trim((string) ($marketing['summary'] ?? ''));
        $highlights = array_values(array_filter(
            array_map(static fn(mixed $item): string => trim((string) $item), is_array($marketing['highlights'] ?? null) ? $marketing['highlights'] : []),
            static fn(string $item): bool => $item !== '',
        ));

        return [
            'summary' => $summary !== '' ? $summary : $defaultSummary,
            'highlights' => $highlights !== [] ? $highlights : $defaultHighlights,
            'featured' => (bool) ($marketing['featured'] ?? false),
            'cta_label' => trim((string) ($marketing['cta_label'] ?? '')) ?: $defaultCtaLabel,
            'cta_href' => trim((string) ($marketing['cta_href'] ?? '')) ?: $defaultCtaHref,
            'custom_pricing' => (bool) ($marketing['custom_pricing'] ?? ($code === 'enterprise')),
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
        string $description,
        float $price,
        string $currency,
        string $billingCycle,
        array $features,
        bool $isFeatured,
        string $ctaLabel,
        string $ctaHref,
        ?int $displayOrder = null,
        bool $isCustomPricing = false,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'currency' => $currency,
            'billing_cycle' => $billingCycle,
            'display_order' => $displayOrder,
            'is_active' => true,
            'summary' => $description,
            'features' => $features,
            'is_featured' => $isFeatured,
            'cta_label' => $ctaLabel,
            'cta_href' => $ctaHref,
            'is_custom_pricing' => $isCustomPricing,
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
            $code === 'free' => 10,
            $code === 'professional' => 20,
            $code === 'enterprise' => 30,
            default => 1000 + (int) round((float) ($plan['price'] ?? 0.0)),
        };
    }
}
