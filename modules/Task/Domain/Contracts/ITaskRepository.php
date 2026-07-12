<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Domain\Contracts;

use WorkEddy\Modules\Task\Domain\Task;

interface ITaskRepository
{
    public function create(Task $task): int;

    public function update(Task $task): void;

    public function delete(string $uuid): void;

    public function findByUuid(string $uuid): ?Task;

    /**
     * @return list<Task>
     */
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array;
}
