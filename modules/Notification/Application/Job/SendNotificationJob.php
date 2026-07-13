<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application\Job;

use WorkEddy\Modules\Notification\Domain\NotificationRequest;

/**
 * Serializes a NotificationRequest into a queue-compatible payload array.
 * This is NOT a QueueJob — it is a payload builder. QueueJob is a platform
 * value object hydrated by the queue worker, not a base class for jobs.
 */
final class SendNotificationJob
{
    public const JOB_TYPE = 'notification.send';

    public function __construct(
        private readonly NotificationRequest $request,
        private readonly int $attemptCount = 1,
        private readonly ?string $logUuid = null,
        private readonly int $channelIndex = 0,
        private readonly int $providerIndex = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'type'              => $this->request->type->value,
            'recipient_id'      => $this->request->recipient->recipientId,
            'recipient_type'    => $this->request->recipient->recipientType,
            'recipient_name'    => $this->request->recipient->name,
            'recipient_email'   => $this->request->recipient->email,
            'recipient_phone'   => $this->request->recipient->phone,
            'data'              => $this->request->data,
            'priority'          => $this->request->priority->value,
            'preferred_channel' => $this->request->preferredChannel?->value,
            'required_channel'  => $this->request->requiredChannel?->value,
            'metadata'          => $this->request->metadata,
            'attempt_count'     => $this->attemptCount,
            'log_uuid'          => $this->logUuid,
            'channel_index'     => $this->channelIndex,
            'provider_index'    => $this->providerIndex,
        ];
    }

    public function getQueueName(): string
    {
        return $this->request->priority->value === 'high' ? 'high_priority' : 'default';
    }
}
