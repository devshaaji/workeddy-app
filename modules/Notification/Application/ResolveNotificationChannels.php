<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationType;

final class ResolveNotificationChannels implements ChannelResolverInterface
{
    private array $rules = [];

    public function __construct()
    {
        $this->rules = [
            'iam.auth_otp' => [NotificationChannel::SMS, NotificationChannel::WHATSAPP, NotificationChannel::EMAIL],
            'iam.password_reset' => [NotificationChannel::EMAIL],
            'iam.password_reset_completed' => [NotificationChannel::EMAIL],
            'iam.password_changed' => [NotificationChannel::EMAIL],
            'iam.role_assigned' => [NotificationChannel::EMAIL],
            'iam.user_activated' => [NotificationChannel::EMAIL],
            'iam.user_created' => [NotificationChannel::EMAIL],
            'iam.user_suspended' => [NotificationChannel::EMAIL],
            'crm.lead_assigned' => [NotificationChannel::IN_APP, NotificationChannel::EMAIL],
            'otp' => [NotificationChannel::SMS, NotificationChannel::WHATSAPP, NotificationChannel::EMAIL],
            'payment_receipt' => [NotificationChannel::IN_APP, NotificationChannel::EMAIL, NotificationChannel::WHATSAPP],
            'invoice_created' => [NotificationChannel::IN_APP, NotificationChannel::EMAIL, NotificationChannel::WHATSAPP],
            'subscription_expired' => [NotificationChannel::IN_APP, NotificationChannel::EMAIL, NotificationChannel::WHATSAPP, NotificationChannel::SMS],
            'customer_suspended' => [NotificationChannel::IN_APP, NotificationChannel::EMAIL, NotificationChannel::SMS, NotificationChannel::WHATSAPP],
            'subscription_activated' => [NotificationChannel::IN_APP, NotificationChannel::EMAIL, NotificationChannel::WHATSAPP],
            'support_ticket_created' => [NotificationChannel::IN_APP, NotificationChannel::EMAIL],
            'system_alert' => [NotificationChannel::EMAIL],
            'edge_sync_failed' => [NotificationChannel::EMAIL, NotificationChannel::SMS],
        ];
    }

    public function resolve(NotificationType $type): array
    {
        return $this->rules[$type->value] ?? [NotificationChannel::EMAIL];
    }
}
