<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Queue;

final class QueueJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $queue,
        public readonly string $jobType,
        public readonly array $payload,
        public readonly string $status,
        public readonly int $attempts,
        public readonly int $maxAttempts,
        public readonly ?string $lockedBy = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'queue' => $this->queue,
            'job_type' => $this->jobType,
            'payload' => $this->payload,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->maxAttempts,
            'locked_by' => $this->lockedBy,
        ];
    }
}
