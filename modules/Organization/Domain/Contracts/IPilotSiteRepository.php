<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain\Contracts;

use WorkEddy\Modules\Organization\Domain\PilotSite;

interface IPilotSiteRepository
{
    public function create(PilotSite $pilotSite): int;

    public function update(PilotSite $pilotSite): void;

    public function findByUuid(string $uuid): ?PilotSite;

    /**
     * @param array<string, mixed> $filters
     * @return list<PilotSite>
     */
    public function findAllByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array;
}
