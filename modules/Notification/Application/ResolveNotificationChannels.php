<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationChannelPolicy;
use WorkEddy\Modules\Notification\Domain\NotificationFallbackMode;
use WorkEddy\Modules\Notification\Domain\NotificationType;

final class ResolveNotificationChannels implements ChannelResolverInterface
{
    /** @var array<string, NotificationChannelPolicy> */
    private array $policies = [];

    public function __construct()
    {
        $this->policies = [
            'iam.auth_otp' => new NotificationChannelPolicy(
                [NotificationChannel::SMS, NotificationChannel::WHATSAPP, NotificationChannel::EMAIL],
                NotificationFallbackMode::ON_FAILURE
            ),
            'iam.password_reset' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'iam.password_reset_completed' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP],
                NotificationFallbackMode::ON_PERMANENT_FAILURE
            ),
            'iam.password_changed' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'iam.role_assigned' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP],
                NotificationFallbackMode::ON_PERMANENT_FAILURE
            ),
            'iam.user_activated' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'iam.user_created' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'iam.user_suspended' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::SMS],
                NotificationFallbackMode::ON_PERMANENT_FAILURE
            ),
            'crm.lead_assigned' => new NotificationChannelPolicy(
                [NotificationChannel::IN_APP, NotificationChannel::EMAIL],
                NotificationFallbackMode::ON_FAILURE
            ),
            'otp' => new NotificationChannelPolicy(
                [NotificationChannel::SMS, NotificationChannel::WHATSAPP, NotificationChannel::EMAIL],
                NotificationFallbackMode::ON_FAILURE
            ),
            'payment_receipt' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP],
                NotificationFallbackMode::ON_FAILURE
            ),
            'invoice_created' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP, NotificationChannel::WHATSAPP],
                NotificationFallbackMode::ON_FAILURE
            ),
            'subscription_expired' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP, NotificationChannel::SMS],
                NotificationFallbackMode::ON_FAILURE
            ),
            'subscription_suspended' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'customer_suspended' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP, NotificationChannel::SMS],
                NotificationFallbackMode::ON_FAILURE
            ),
            'subscription_activated' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP],
                NotificationFallbackMode::ON_FAILURE
            ),
            'subscription_renewed' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'subscription_plan_changed' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'subscription_cancelled' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL],
                NotificationFallbackMode::NEVER
            ),
            'support_ticket_created' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::IN_APP],
                NotificationFallbackMode::ON_FAILURE
            ),
            'system_alert' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::SMS],
                NotificationFallbackMode::ON_FAILURE
            ),
            'edge_sync_failed' => new NotificationChannelPolicy(
                [NotificationChannel::EMAIL, NotificationChannel::SMS],
                NotificationFallbackMode::ON_FAILURE
            ),
        ];
    }

    public function resolve(NotificationType $type): array
    {
        return $this->policy($type)->channels;
    }

    public function policy(NotificationType $type): NotificationChannelPolicy
    {
        return $this->policies[$type->value]
            ?? new NotificationChannelPolicy([NotificationChannel::EMAIL], NotificationFallbackMode::NEVER);
    }
}
