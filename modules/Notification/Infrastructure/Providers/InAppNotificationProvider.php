<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Providers;

use WorkEddy\Modules\Notification\Contracts\InAppNotificationRepositoryInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationProviderInterface;
use WorkEddy\Modules\Notification\Domain\InAppNotification;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationMessage;
use WorkEddy\Modules\Notification\Domain\NotificationProviderResult;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Identity\UuidGeneratorContract;

final class InAppNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private readonly InAppNotificationRepositoryInterface $repository,
        private readonly UuidGeneratorContract $uuidGenerator,
        private readonly IClock $clock,
    ) {}

    public function channel(): string
    {
        return NotificationChannel::IN_APP->value;
    }

    public function send(NotificationMessage $message): NotificationProviderResult
    {
        $uuid = $this->uuidGenerator->generate();

        $this->repository->save(new InAppNotification(
            uuid: $uuid,
            recipientType: $message->recipient->recipientType,
            recipientId: $message->recipient->recipientId,
            notificationType: (string) ($message->metadata['notification_type'] ?? 'notification'),
            subject: $message->subject,
            body: $message->body,
            metadataJson: $message->metadata,
            createdAt: $this->clock->now(),
            updatedAt: $this->clock->now(),
        ));

        return new NotificationProviderResult(
            success: true,
            providerMessageId: $uuid,
        );
    }
}
