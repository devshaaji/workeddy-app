<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationDeliveryLog;
use WorkEddy\Modules\Notification\Domain\NotificationDispatchResult;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Application\Job\SendNotificationJob;
use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Clock\IClock;

final class NotificationService implements NotificationServiceInterface
{
    public function __construct(
        private readonly IQueueService $queueService,
        private readonly NotificationLogRepositoryInterface $logRepository,
        private readonly ChannelResolverInterface $channelResolver,
        private readonly UuidGeneratorContract $uuidGenerator,
        private readonly IClock $clock,
        private readonly ResolveRecipientNotificationChannels $recipientChannelResolver,
    ) {}

    public function send(NotificationRequest $request): NotificationDispatchResult
    {
        $uuid = $this->uuidGenerator->generate();

        $channels = $this->recipientChannelResolver->resolve($request);
        $primaryChannel = $request->requiredChannel ?? $request->preferredChannel ?? $channels[0] ?? \WorkEddy\Modules\Notification\Domain\NotificationChannel::EMAIL;

        $log = new NotificationDeliveryLog(
            uuid: $uuid,
            notificationType: $request->type->value,
            recipientType: $request->recipient->recipientType,
            recipientId: $request->recipient->recipientId,
            channel: $primaryChannel,
            provider: 'pending',
            status: 'queued',
            recipientName: $request->recipient->name,
            recipientEmail: $request->recipient->email,
            recipientPhone: $request->recipient->phone,
            metadataJson: $request->metadata,
            queuedAt: $this->clock->now()
        );

        $this->logRepository->save($log);

        $job = new SendNotificationJob($request, 1, $uuid);

        $this->queueService->dispatch(
            jobType: SendNotificationJob::JOB_TYPE,
            payload: $job->toPayload(),
            queue: $job->getQueueName(),
        );

        return new NotificationDispatchResult(
            success: true,
            message: 'Notification queued for delivery.'
        );
    }
}
