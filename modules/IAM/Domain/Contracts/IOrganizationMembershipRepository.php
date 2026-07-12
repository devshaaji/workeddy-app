<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Modules\IAM\Domain\OrganizationMembership;

interface IOrganizationMembershipRepository
{
    public function create(OrganizationMembership $membership): int;
    public function update(OrganizationMembership $membership): void;
    public function delete(string $uuid): void;
    public function findByUuid(string $uuid): ?OrganizationMembership;
    public function findPrimaryByUserId(int|string $userId): ?OrganizationMembership;
    public function findByUserAndOrganizationUuid(int|string $userId, string $organizationUuid): ?OrganizationMembership;

    /**
     * @return list<OrganizationMembership>
     */
    public function findAllByOrganizationId(int $organizationId, int $limit = 50, int $offset = 0): array;

    /**
     * @return list<OrganizationMembership>
     */
    public function findAllByUserId(int|string $userId): array;
}
