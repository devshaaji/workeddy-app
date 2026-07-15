<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Seeding\SeederInterface;

return new class implements SeederInterface
{
    private const PLANS = [
        [
            'code'          => 'free',
            'name'          => 'Pilot',
            'description'   => 'For organizations evaluating WorkEddy with a limited number of authorized tasks and users.',
            'price'         => '0.00',
            'display_order' => 1,
            'features'      => [
                'max_worksites'                => 1,
                'video_scan_limit'              => 5,
                'live_session_limit'            => 5,
                'live_session_minutes_limit'    => 60,
                'max_assessments_per_month'     => 10,
                'video_storage_gb'              => 1,
                'max_video_retention_days'      => 30,
                'max_users'                     => 3,
                'max_live_concurrent_sessions'  => 1,
                'marketing'                     => [
                    'summary' => 'For organizations evaluating WorkEddy with a limited number of authorized tasks and users.',
                    'highlights' => [
                        'Guided pilot onboarding',
                        'Structured ergonomic assessments',
                        'Worker feedback intake',
                        'Basic reporting exports',
                    ],
                    'cta_label' => 'Request Pilot',
                    'cta_href' => '/register',
                    'featured' => false,
                    'custom_pricing' => false,
                ],
            ],
        ],
        [
            'code'          => 'professional',
            'name'          => 'Professional',
            'description'   => 'For organizations managing ongoing ergonomic assessment and corrective-action workflows.',
            'price'         => '299.00',
            'display_order' => 2,
            'features'      => [
                'max_worksites'                => 5,
                'video_scan_limit'              => 500,
                'live_session_limit'            => 250,
                'live_session_minutes_limit'    => 3000,
                'max_assessments_per_month'     => 500,
                'video_storage_gb'              => 100,
                'max_video_retention_days'      => 180,
                'max_users'                     => 50,
                'max_live_concurrent_sessions'  => 4,
                'marketing'                     => [
                    'summary' => 'For organizations managing ongoing ergonomic assessment and corrective-action workflows.',
                    'highlights' => [
                        'Ongoing assessment workflows',
                        'Corrective action tracking',
                        'Before-and-after reassessment',
                        'Dashboard reporting and filtering',
                    ],
                    'cta_label' => 'Start Professional Trial',
                    'cta_href' => '/register',
                    'featured' => true,
                    'custom_pricing' => false,
                ],
            ],
        ],
        [
            'code'          => 'enterprise',
            'name'          => 'Multi-site',
            'description'   => 'For organizations requiring additional worksites, governance controls, reporting, and implementation support.',
            'price' => '999.00',
            'display_order' => 3,
            'features'      => [
                'max_worksites'                => null,
                'video_scan_limit'              => null,
                'live_session_limit'            => null,
                'live_session_minutes_limit'    => null,
                'max_assessments_per_month'     => null,
                'video_storage_gb'              => null,
                'max_video_retention_days'      => 365,
                'max_users'                     => null,
                'max_live_concurrent_sessions'  => 12,
                'marketing'                     => [
                    'summary' => 'For organizations requiring additional worksites, governance controls, reporting, and implementation support.',
                    'highlights' => [
                        'Additional worksites',
                        'Governance and role controls',
                        'Consolidated reporting',
                        'Implementation support',
                    ],
                    'cta_label' => 'Contact Sales',
                    'cta_href' => '/contact-us',
                    'featured' => false,
                    'custom_pricing' => true,
                ],
            ],
        ],
    ];

    public function run(Connection $db): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // Deactivate legacy starter plan if it exists
        $db->update('subscription_plans', ['is_active' => 0], ['code' => 'starter']);

        foreach (self::PLANS as $plan) {
            $existing = $db->fetchAssociative(
                'SELECT id FROM subscription_plans WHERE code = ?',
                [$plan['code']],
            );

            $payload = [
                'code'          => $plan['code'],
                'name'          => $plan['name'],
                'description'   => $plan['description'],
                'billing_cycle' => 'monthly',
                'price'         => $plan['price'],
                'currency'      => 'USD',
                'features'      => json_encode($plan['features'], JSON_THROW_ON_ERROR),
                'is_active'     => 1,
                'display_order' => $plan['display_order'],
                'updated_at'    => $now,
            ];

            if ($existing === false) {
                $db->insert('subscription_plans', $payload + [
                    'created_at' => $now,
                ]);
                continue;
            }

            $db->update('subscription_plans', $payload, [
                'id' => (int) $existing['id'],
            ]);
        }
    }
};
