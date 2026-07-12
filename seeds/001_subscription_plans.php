<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Seeding\SeederInterface;

return new class implements SeederInterface
{
    private const PLANS = [
        [
            'code'          => 'starter',
            'name'          => 'Starter',
            'description'   => 'For small teams getting started with ergonomics.',
            'price'         => '0.00',
            'display_order' => 1,
            'features'      => [
                'max_worksites'                => 1,
                'video_scan_limit'              => 5,
                'live_session_limit'            => 5,
                'live_session_minutes_limit'    => 60,
                'max_assessments_per_month'     => 10,
                'video_storage_gb'              => 1,
                'ai_scoring_credits_per_month'  => 10,
                'max_video_retention_days'      => 7,
                'max_users'                     => 3,
                'max_live_concurrent_sessions'  => 1,
            ],
        ],
        [
            'code'          => 'professional',
            'name'          => 'Professional',
            'description'   => 'For growing safety teams with higher scan volumes.',
            'price'         => '299.00',
            'display_order' => 2,
            'features'      => [
                'max_worksites'                => 5,
                'video_scan_limit'              => 500,
                'live_session_limit'            => 250,
                'live_session_minutes_limit'    => 3000,
                'max_assessments_per_month'     => 500,
                'video_storage_gb'              => 100,
                'ai_scoring_credits_per_month'  => 500,
                'max_video_retention_days'      => 180,
                'max_users'                     => 50,
                'max_live_concurrent_sessions'  => 4,
            ],
        ],
        [
            'code'          => 'enterprise',
            'name'          => 'Enterprise',
            'description'   => 'Unlimited scans and members for large organisations.',
            'price'         => '999.00',
            'display_order' => 3,
            'features'      => [
                'max_worksites'                => null,
                'video_scan_limit'              => null,
                'live_session_limit'            => null,
                'live_session_minutes_limit'    => null,
                'max_assessments_per_month'     => null,
                'video_storage_gb'              => null,
                'ai_scoring_credits_per_month'  => null,
                'max_video_retention_days'      => 3650,
                'max_users'                     => null,
                'max_live_concurrent_sessions'  => 12,
            ],
        ],
    ];

    public function run(Connection $db): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

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
