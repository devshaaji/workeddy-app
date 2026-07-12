<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Domain\Contracts;

use WorkEddy\Modules\Privacy\Domain\RetentionPolicy;

interface IPrivacyRepository
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createConsent(array $data): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createVideoAccessLog(array $data): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listVideoConsents(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listVideoAccessLogs(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listVideoAssetActivity(string $organizationUuid, string $assessmentUuid, string $storageFileUuid, int $limit = 20): array;

    public function upsertRetentionPolicy(RetentionPolicy $policy): RetentionPolicy;

    public function findRetentionPolicyByOrganizationId(int $organizationId): ?RetentionPolicy;

    /**
     * @return list<RetentionPolicy>
     */
    public function listRetentionPolicies(int $limit = 100, int $offset = 0): array;
}
