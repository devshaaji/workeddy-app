<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Queue;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

final class DbalQueueService implements IQueueService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly int $defaultMaxAttempts = 3,
    ) {}

    public function dispatch(string $jobType, array $payload, ?string $queue = null, int $delaySeconds = 0): void
    {
        $now = self::now();
        $this->connection->insert('platform_jobs', [
            'job_id' => Uuid::uuid7()->toString(),
            'queue' => $queue ?? 'default',
            'job_type' => $jobType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => max(1, $this->defaultMaxAttempts),
            'available_at' => (new \DateTimeImmutable('+' . max(0, $delaySeconds) . ' seconds'))->format('Y-m-d H:i:s.u'),
            'locked_by' => null,
            'locked_until' => null,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'completed_at' => null,
            'failed_at' => null,
        ]);
    }

    public function claimAvailable(string $queue, string $workerId, int $limit, int $lockSeconds): array
    {
        $now = self::now();
        $lockUntil = (new \DateTimeImmutable('+' . max(10, $lockSeconds) . ' seconds'))->format('Y-m-d H:i:s.u');

        return $this->connection->transactional(function () use ($queue, $workerId, $limit, $now, $lockUntil): array {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT *
                 FROM platform_jobs
                 WHERE queue = :queue
                   AND status IN (:queued, :failed)
                   AND available_at <= :now
                   AND (locked_until IS NULL OR locked_until < :now)
                 ORDER BY available_at ASC, created_at ASC
                 LIMIT ' . max(1, min(500, $limit)),
                ['queue' => $queue, 'queued' => 'queued', 'failed' => 'failed', 'now' => $now],
            );

            $claimedRows = [];
            foreach ($rows as $row) {
                $affected = $this->connection->executeStatement(
                    'UPDATE platform_jobs
                     SET status = :processing,
                         attempts = attempts + 1,
                         locked_by = :worker_id,
                         locked_until = :lock_until,
                         updated_at = :updated_at
                     WHERE job_id = :job_id
                       AND queue = :queue
                       AND status IN (:queued, :failed)
                       AND available_at <= :now
                       AND (locked_until IS NULL OR locked_until < :now)',
                    [
                        'processing' => 'processing',
                        'worker_id' => $workerId,
                        'lock_until' => $lockUntil,
                        'updated_at' => $now,
                        'job_id' => (string) $row['job_id'],
                        'queue' => $queue,
                        'queued' => 'queued',
                        'failed' => 'failed',
                        'now' => $now,
                    ],
                );
                if ($affected !== 1) {
                    continue;
                }

                $row['status'] = 'processing';
                $row['attempts'] = ((int) $row['attempts']) + 1;
                $row['locked_by'] = $workerId;
                $claimedRows[] = $row;
            }

            return array_map(fn(array $row): QueueJob => $this->jobFromRow($row), $claimedRows);
        });
    }

    public function complete(string $jobId, string $workerId): void
    {
        $now = self::now();
        $this->connection->update('platform_jobs', [
            'status' => 'completed',
            'locked_by' => null,
            'locked_until' => null,
            'last_error' => null,
            'completed_at' => $now,
            'updated_at' => $now,
        ], ['job_id' => $jobId, 'locked_by' => $workerId]);
    }

    public function fail(string $jobId, string $workerId, string $error, int $retryDelaySeconds): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT attempts, max_attempts FROM platform_jobs WHERE job_id = :job_id AND locked_by = :locked_by',
            ['job_id' => $jobId, 'locked_by' => $workerId],
        );
        if ($row === false) {
            return;
        }

        $now = self::now();
        $attempts = (int) $row['attempts'];
        $maxAttempts = (int) $row['max_attempts'];
        $dead = $attempts >= $maxAttempts;

        $this->connection->update('platform_jobs', [
            'status' => $dead ? 'dead' : 'failed',
            'available_at' => $dead ? $now : (new \DateTimeImmutable('+' . max(1, $retryDelaySeconds) . ' seconds'))->format('Y-m-d H:i:s.u'),
            'locked_by' => null,
            'locked_until' => null,
            'last_error' => substr($error, 0, 2000),
            'failed_at' => $now,
            'updated_at' => $now,
        ], ['job_id' => $jobId, 'locked_by' => $workerId]);
    }

    public function retryDead(string $queue, int $limit): int
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT job_id FROM platform_jobs WHERE queue = :queue AND status = :dead ORDER BY failed_at ASC LIMIT ' . max(1, min(500, $limit)),
            ['queue' => $queue, 'dead' => 'dead'],
        );
        $now = self::now();
        foreach ($rows as $row) {
            $this->connection->update('platform_jobs', [
                'status' => 'queued',
                'attempts' => 0,
                'available_at' => $now,
                'locked_by' => null,
                'locked_until' => null,
                'last_error' => null,
                'updated_at' => $now,
            ], ['job_id' => (string) $row['job_id']]);
        }

        return count($rows);
    }

    public function releaseStaleLocks(int $limit): int
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT job_id FROM platform_jobs WHERE status = :processing AND locked_until < :now ORDER BY locked_until ASC LIMIT ' . max(1, min(500, $limit)),
            ['processing' => 'processing', 'now' => self::now()],
        );
        $now = self::now();
        foreach ($rows as $row) {
            $this->connection->update('platform_jobs', [
                'status' => 'failed',
                'locked_by' => null,
                'locked_until' => null,
                'last_error' => 'Worker lock expired before completion.',
                'available_at' => $now,
                'failed_at' => $now,
                'updated_at' => $now,
            ], ['job_id' => (string) $row['job_id']]);
        }

        return count($rows);
    }

    public function counts(?string $queue = null): array
    {
        $params = [];
        $where = '';
        if ($queue !== null && $queue !== '') {
            $where = ' WHERE queue = :queue';
            $params['queue'] = $queue;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT status, COUNT(*) AS total FROM platform_jobs' . $where . ' GROUP BY status',
            $params,
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function jobFromRow(array $row): QueueJob
    {
        $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);

        return new QueueJob(
            (string) $row['job_id'],
            (string) $row['queue'],
            (string) $row['job_type'],
            is_array($payload) ? $payload : [],
            (string) $row['status'],
            (int) $row['attempts'],
            (int) $row['max_attempts'],
            $row['locked_by'] !== null ? (string) $row['locked_by'] : null,
        );
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
