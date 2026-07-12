<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Queue;

interface IQueueService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $jobType, array $payload, ?string $queue = null, int $delaySeconds = 0): void;

    /**
     * @return list<QueueJob>
     */
    public function claimAvailable(string $queue, string $workerId, int $limit, int $lockSeconds): array;

    public function complete(string $jobId, string $workerId): void;

    public function fail(string $jobId, string $workerId, string $error, int $retryDelaySeconds): void;

    public function retryDead(string $queue, int $limit): int;

    public function releaseStaleLocks(int $limit): int;

    /**
     * @return array<string, int>
     */
    public function counts(?string $queue = null): array;
}
