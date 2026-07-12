<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Queue;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class QueueWorker
{
    public function __construct(
        private readonly IQueueService $queue,
        private readonly QueueHandlerRegistry $handlers,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @return array{queue:string, worker_id:string, claimed:int, completed:int, failed:int, dead:int}
     */
    public function work(string $queue, string $workerId, int $limit, int $lockSeconds, int $retryDelaySeconds): array
    {
        $claimed = $this->queue->claimAvailable($queue, $workerId, max(1, min(500, $limit)), max(10, $lockSeconds));
        $completed = 0;
        $failed = 0;
        $dead = 0;

        foreach ($claimed as $job) {
            $handler = $this->handlers->get($job->jobType);
            if (!$handler instanceof QueueJobHandlerInterface) {
                $this->logger->warning('Queue job failed because no handler is registered.', [
                    'job_id' => $job->jobId,
                    'job_type' => $job->jobType,
                    'queue' => $queue,
                    'worker_id' => $workerId,
                    'attempts' => $job->attempts,
                    'max_attempts' => $job->maxAttempts,
                ]);
                $this->queue->fail($job->jobId, $workerId, "No queue handler registered for {$job->jobType}", $retryDelaySeconds);
                $failed++;
                if ($job->attempts >= $job->maxAttempts) {
                    $dead++;
                }
                continue;
            }

            try {
                $handler->handle($job);
                $this->queue->complete($job->jobId, $workerId);
                $completed++;
            } catch (\Throwable $exception) {
                $this->logger->error('Queue job handler failed.', [
                    'job_id' => $job->jobId,
                    'job_type' => $job->jobType,
                    'queue' => $queue,
                    'worker_id' => $workerId,
                    'attempts' => $job->attempts,
                    'max_attempts' => $job->maxAttempts,
                    'exception' => $exception,
                ]);
                $this->queue->fail($job->jobId, $workerId, $exception->getMessage(), $retryDelaySeconds);
                $failed++;
                if ($job->attempts >= $job->maxAttempts) {
                    $dead++;
                }
            }
        }

        return [
            'queue' => $queue,
            'worker_id' => $workerId,
            'claimed' => count($claimed),
            'completed' => $completed,
            'failed' => $failed,
            'dead' => $dead,
        ];
    }
}
