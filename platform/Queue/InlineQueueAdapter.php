<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Queue;

final class InlineQueueAdapter implements IQueueService
{
    /** @var list<array{jobType: string, payload: array<string, mixed>, queue: string}> */
    private array $dispatched = [];

    /** @var array<string, QueueJob> */
    private array $jobs = [];

    public function __construct(private readonly int $defaultMaxAttempts = 3) {}

    public function dispatch(string $jobType, array $payload, ?string $queue = null, int $delaySeconds = 0): void
    {
        $queueName = $queue ?? 'default';
        $this->dispatched[] = [
            'jobType' => $jobType,
            'payload' => $payload,
            'queue' => $queueName,
        ];

        $jobId = 'inline-' . (count($this->jobs) + 1);
        $this->jobs[$jobId] = new QueueJob($jobId, $queueName, $jobType, $payload, 'queued', 0, max(1, $this->defaultMaxAttempts));
    }

    public function claimAvailable(string $queue, string $workerId, int $limit, int $lockSeconds): array
    {
        $claimed = [];
        foreach ($this->jobs as $jobId => $job) {
            if ($job->queue !== $queue || !in_array($job->status, ['queued', 'failed'], true)) {
                continue;
            }

            $claimedJob = new QueueJob($job->jobId, $job->queue, $job->jobType, $job->payload, 'processing', $job->attempts + 1, $job->maxAttempts, $workerId);
            $this->jobs[$jobId] = $claimedJob;
            $claimed[] = $claimedJob;
            if (count($claimed) >= max(1, $limit)) {
                break;
            }
        }

        return $claimed;
    }

    public function complete(string $jobId, string $workerId): void
    {
        $job = $this->jobs[$jobId] ?? null;
        if ($job instanceof QueueJob && $job->lockedBy === $workerId) {
            $this->jobs[$jobId] = new QueueJob($job->jobId, $job->queue, $job->jobType, $job->payload, 'completed', $job->attempts, $job->maxAttempts);
        }
    }

    public function fail(string $jobId, string $workerId, string $error, int $retryDelaySeconds): void
    {
        $job = $this->jobs[$jobId] ?? null;
        if (!$job instanceof QueueJob || $job->lockedBy !== $workerId) {
            return;
        }

        $status = $job->attempts >= $job->maxAttempts ? 'dead' : 'failed';
        $this->jobs[$jobId] = new QueueJob($job->jobId, $job->queue, $job->jobType, $job->payload, $status, $job->attempts, $job->maxAttempts);
    }

    public function retryDead(string $queue, int $limit): int
    {
        $retried = 0;
        foreach ($this->jobs as $jobId => $job) {
            if ($job->queue !== $queue || $job->status !== 'dead') {
                continue;
            }

            $this->jobs[$jobId] = new QueueJob($job->jobId, $job->queue, $job->jobType, $job->payload, 'queued', 0, $job->maxAttempts);
            $retried++;
            if ($retried >= max(1, $limit)) {
                break;
            }
        }

        return $retried;
    }

    public function releaseStaleLocks(int $limit): int
    {
        return 0;
    }

    public function counts(?string $queue = null): array
    {
        $counts = [];
        foreach ($this->jobs as $job) {
            if ($queue !== null && $job->queue !== $queue) {
                continue;
            }

            $counts[$job->status] = ($counts[$job->status] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return list<array{jobType: string, payload: array<string, mixed>, queue: string}>
     */
    public function dispatched(): array
    {
        return $this->dispatched;
    }

    public function reset(): void
    {
        $this->dispatched = [];
        $this->jobs = [];
    }
}
