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
                'video_scan_limit'              => 5,
                'live_session_limit'            => 5,
                'live_session_minutes_limit'    => 60,
                'llm_request_limit'             => 10,
                'llm_token_limit'               => 1000,
                'max_video_retention_days'      => 7,
                'max_org_members'               => 3,
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
                'video_scan_limit'              => 500,
                'live_session_limit'            => 250,
                'live_session_minutes_limit'    => 3000,
                'llm_request_limit'             => 500,
                'llm_token_limit'               => 2000000,
                'max_video_retention_days'      => 180,
                'max_org_members'               => 50,
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
                'video_scan_limit'              => null,
                'live_session_limit'            => null,
                'live_session_minutes_limit'    => null,
                'llm_request_limit'             => null,
                'llm_token_limit'               => null,
                'max_video_retention_days'      => 3650,
                'max_org_members'               => null,
                'max_live_concurrent_sessions'  => 12,
            ],
        ],
    ];

    public function run(Connection $db): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach (self::PLANS as $plan) {
            $exists = $db->fetchOne(
                'SELECT COUNT(*) FROM subscription_plans WHERE code = ?',
                [$plan['code']],
            );

            if ((int) $exists > 0) {
                continue;
            }

            $db->insert('subscription_plans', [
                'code'          => $plan['code'],
                'name'          => $plan['name'],
                'description'   => $plan['description'],
                'billing_cycle' => 'monthly',
                'price'         => $plan['price'],
                'currency'      => 'USD',
                'features'      => json_encode($plan['features'], JSON_THROW_ON_ERROR),
                'is_active'     => 1,
                'display_order' => $plan['display_order'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
};
