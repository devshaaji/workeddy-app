<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application\Job;

use WorkEddy\Modules\Notification\Application\SendNotificationUseCase;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationPriority;
use WorkEddy\Modules\Notification\Domain\NotificationRecipient;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Platform\Queue\QueueJob;
use WorkEddy\Platform\Queue\QueueJobHandlerInterface;

final class SendNotificationJobHandler implements QueueJobHandlerInterface
{
    public function __construct(
        private readonly SendNotificationUseCase $useCase,
    ) {}

    public function handle(QueueJob $job): void
    {
        $p = $job->payload;

        $request = new NotificationRequest(
            type: new NotificationType($p['type']),
            recipient: new NotificationRecipient(
                recipientId: $p['recipient_id'],
                recipientType: $p['recipient_type'],
                name: $p['recipient_name'] ?? null,
                email: $p['recipient_email'] ?? null,
                phone: $p['recipient_phone'] ?? null,
            ),
            data: $p['data'] ?? [],
            priority: NotificationPriority::from($p['priority'] ?? 'normal'),
            preferredChannel: isset($p['preferred_channel']) ? NotificationChannel::from($p['preferred_channel']) : null,
            requiredChannel: isset($p['required_channel'])  ? NotificationChannel::from($p['required_channel'])  : null,
            metadata: $p['metadata'] ?? [],
        );

        $this->useCase->execute($request, (int) ($p['attempt_count'] ?? 1), $p['log_uuid'] ?? null);
    }
}
