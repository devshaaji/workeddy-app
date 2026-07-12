<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain\Contracts;

use WorkEddy\Modules\Organization\Domain\Worksite;

interface IWorksiteRepository
{
    public function create(Worksite $worksite): int;
    public function update(Worksite $worksite): void;
    public function delete(string $uuid): void;
    public function findByUuid(string $uuid): ?Worksite;
    public function findById(int $id): ?Worksite;

    /**
     * @return list<Worksite>
     */
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array;
}
