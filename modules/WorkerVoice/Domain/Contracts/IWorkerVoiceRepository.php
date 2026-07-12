<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Domain\Contracts;

use WorkEddy\Modules\WorkerVoice\Domain\WorkerFeedback;

interface IWorkerVoiceRepository
{
    public function create(WorkerFeedback $feedback): int;

    public function findByUuid(string $uuid): ?WorkerFeedback;

    /**
     * @param array<string, mixed> $filters
     * @return list<WorkerFeedback>
     */
    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array;
}
