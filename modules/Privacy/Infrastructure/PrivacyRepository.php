<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\Privacy\Domain\RetentionPolicy;
use WorkEddy\Platform\Clock\IClock;

final class PrivacyRepository implements IPrivacyRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function createConsent(array $data): array
    {
        $this->connection->insert('privacy_video_consents', [
            'uuid' => $data['uuid'],
            'organization_uuid' => $data['organizationUuid'],
            'assessment_uuid' => $data['assessmentUuid'],
            'storage_file_uuid' => $data['storageFileUuid'],
            'user_id' => $data['userId'],
            'text_version' => $data['textVersion'],
            'accepted_notice' => $data['acceptedNotice'] ? 1 : 0,
            'ip_address' => $data['ipAddress'],
            'user_agent' => $data['userAgent'],
            'accepted_at' => $data['acceptedAt'],
            'created_at' => $this->now(),
        ]);

        return $data;
    }

    public function createVideoAccessLog(array $data): array
    {
        $this->connection->insert('privacy_video_access_logs', [
            'uuid' => $data['uuid'],
            'organization_uuid' => $data['organizationUuid'],
            'assessment_uuid' => $data['assessmentUuid'],
            'storage_file_uuid' => $data['storageFileUuid'],
            'user_id' => $data['userId'],
            'purpose' => $data['purpose'],
            'ip_address' => $data['ipAddress'],
            'user_agent' => $data['userAgent'],
            'accessed_at' => $data['accessedAt'],
            'created_at' => $this->now(),
        ]);

        return $data;
    }

    public function listVideoConsents(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('privacy_video_consents')
            ->orderBy('accepted_at', 'DESC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->setFirstResult(max(0, $offset));

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $qb->where('organization_uuid = :organizationUuid')
                ->setParameter('organizationUuid', $organizationUuid);
        }

        return array_map([$this, 'mapConsentRow'], $qb->executeQuery()->fetchAllAssociative());
    }

    public function listVideoAccessLogs(?string $organizationUuid = null, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('privacy_video_access_logs')
            ->orderBy('accessed_at', 'DESC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->setFirstResult(max(0, $offset));

        if ($organizationUuid !== null && $organizationUuid !== '') {
            $qb->where('organization_uuid = :organizationUuid')
                ->setParameter('organizationUuid', $organizationUuid);
        }

        return array_map([$this, 'mapVideoAccessLogRow'], $qb->executeQuery()->fetchAllAssociative());
    }

    public function listVideoAssetActivity(string $organizationUuid, string $assessmentUuid, string $storageFileUuid, int $limit = 20): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('privacy_video_access_logs')
            ->where('organization_uuid = :organizationUuid')
            ->andWhere('assessment_uuid = :assessmentUuid')
            ->andWhere('storage_file_uuid = :storageFileUuid')
            ->setParameter('organizationUuid', $organizationUuid)
            ->setParameter('assessmentUuid', $assessmentUuid)
            ->setParameter('storageFileUuid', $storageFileUuid)
            ->orderBy('accessed_at', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)))
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map([$this, 'mapVideoAccessLogRow'], $rows);
    }

    public function upsertRetentionPolicy(RetentionPolicy $policy): RetentionPolicy
    {
        $existing = $this->findRetentionPolicyByOrganizationId($policy->organizationId);
        $now = $this->now();
        $data = [
            'organization_id' => $policy->organizationId,
            'organization_uuid' => $policy->organizationUuid,
            'raw_video_policy' => $policy->rawVideoPolicy,
            'retain_screenshots_only' => $policy->retainScreenshotsOnly ? 1 : 0,
            'retain_for_pilot_evidence' => $policy->retainForPilotEvidence ? 1 : 0,
            'retention_days' => $policy->retentionDays,
            'updated_by' => $policy->updatedBy,
            'updated_at' => $now,
        ];

        if ($existing === null) {
            $this->connection->insert('privacy_retention_policies', $data + ['created_at' => $now]);
        } else {
            $this->connection->update('privacy_retention_policies', $data, ['organization_id' => $policy->organizationId]);
        }

        return $this->findRetentionPolicyByOrganizationId($policy->organizationId) ?? $policy;
    }

    public function findRetentionPolicyByOrganizationId(int $organizationId): ?RetentionPolicy
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM privacy_retention_policies WHERE organization_id = ?', [$organizationId]);

        return $row === false ? null : $this->hydrateRetentionPolicy($row);
    }

    public function listRetentionPolicies(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('privacy_retention_policies')
            ->orderBy('organization_id', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->setFirstResult(max(0, $offset))
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): RetentionPolicy => $this->hydrateRetentionPolicy($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function hydrateRetentionPolicy(array $row): RetentionPolicy
    {
        return new RetentionPolicy(
            id: (int) $row['id'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: (string) $row['organization_uuid'],
            rawVideoPolicy: (string) $row['raw_video_policy'],
            retainScreenshotsOnly: (bool) $row['retain_screenshots_only'],
            retainForPilotEvidence: (bool) $row['retain_for_pilot_evidence'],
            retentionDays: (int) $row['retention_days'],
            updatedBy: (int) $row['updated_by'],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapConsentRow(array $row): array
    {
        return [
            'uuid' => (string) $row['uuid'],
            'organizationUuid' => (string) $row['organization_uuid'],
            'assessmentUuid' => (string) $row['assessment_uuid'],
            'storageFileUuid' => (string) $row['storage_file_uuid'],
            'userId' => (int) $row['user_id'],
            'textVersion' => (string) $row['text_version'],
            'acceptedNotice' => (bool) $row['accepted_notice'],
            'ipAddress' => isset($row['ip_address']) ? (string) $row['ip_address'] : null,
            'userAgent' => isset($row['user_agent']) ? (string) $row['user_agent'] : null,
            'acceptedAt' => isset($row['accepted_at']) ? (string) $row['accepted_at'] : null,
            'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapVideoAccessLogRow(array $row): array
    {
        return [
            'uuid' => (string) $row['uuid'],
            'organizationUuid' => (string) $row['organization_uuid'],
            'assessmentUuid' => (string) $row['assessment_uuid'],
            'storageFileUuid' => (string) $row['storage_file_uuid'],
            'userId' => (int) $row['user_id'],
            'purpose' => (string) $row['purpose'],
            'ipAddress' => isset($row['ip_address']) ? (string) $row['ip_address'] : null,
            'userAgent' => isset($row['user_agent']) ? (string) $row['user_agent'] : null,
            'accessedAt' => isset($row['accessed_at']) ? (string) $row['accessed_at'] : null,
            'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : null,
        ];
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
