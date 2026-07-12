<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Domain\Contracts;

use WorkEddy\Modules\WorkerVoice\Domain\SupervisorFeedback;

interface ISupervisorFeedbackRepository
{
    public function create(SupervisorFeedback $feedback): int;

    public function findByUuid(string $uuid): ?SupervisorFeedback;

    /**
     * @param array<string, mixed> $filters
     * @return list<SupervisorFeedback>
     */
    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array;
}
