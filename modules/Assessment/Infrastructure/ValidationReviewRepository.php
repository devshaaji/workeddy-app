<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Assessment\Domain\Contracts\IValidationReviewRepository;
use WorkEddy\Modules\Assessment\Domain\ValidationReview;

final class ValidationReviewRepository implements IValidationReviewRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function create(ValidationReview $review): int
    {
        $now = date('Y-m-d H:i:s');
        $this->connection->insert('validation_reviews', [
            'uuid' => $review->uuid,
            'organization_id' => $review->organizationId,
            'organization_uuid' => $review->organizationUuid,
            'assessment_uuid' => $review->assessmentUuid,
            'assessment_version' => $review->assessmentVersion,
            'reviewer_user_id' => $review->reviewerUserId,
            'reviewer_name' => $review->reviewerName,
            'reviewer_credentials' => $review->reviewerCredentials,
            'review_round' => $review->reviewRound,
            'score_json' => $this->encode($review->score),
            'risk_level' => $review->riskLevel,
            'body_regions_json' => $this->encode($review->bodyRegions),
            'risk_factors_json' => $this->encode($review->riskFactors),
            'notes' => $review->notes,
            'is_primary' => $review->isPrimary ? 1 : 0,
            'is_final' => $review->isFinal ? 1 : 0,
            'submitted_at' => $review->submittedAt ?? $now,
            'created_at' => $review->createdAt ?? $now,
            'updated_at' => $review->updatedAt ?? $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findByUuid(string $uuid): ?ValidationReview
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM validation_reviews WHERE uuid = ?', [$uuid]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByAssessmentUuid(string $assessmentUuid, bool $finalOnly = false): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('validation_reviews')
            ->where('assessment_uuid = :assessmentUuid')
            ->setParameter('assessmentUuid', $assessmentUuid)
            ->orderBy('submitted_at', 'ASC');

        if ($finalOnly) {
            $qb->andWhere('is_final = 1');
        }

        return array_map(fn(array $row): ValidationReview => $this->hydrate($row), $qb->executeQuery()->fetchAllAssociative());
    }

    public function findByOrganizationId(int $organizationId, array $filters = [], bool $finalOnly = false, int $limit = 500, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('validation_reviews')
            ->where('organization_id = :organizationId')
            ->setParameter('organizationId', $organizationId)
            ->orderBy('submitted_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($finalOnly) {
            $qb->andWhere('is_final = 1');
        }
        if (is_string($filters['assessmentUuid'] ?? null) && trim($filters['assessmentUuid']) !== '') {
            $qb->andWhere('assessment_uuid = :assessmentUuid')->setParameter('assessmentUuid', trim((string) $filters['assessmentUuid']));
        }
        if (is_string($filters['riskLevel'] ?? null) && trim($filters['riskLevel']) !== '') {
            $qb->andWhere('risk_level = :riskLevel')->setParameter('riskLevel', trim((string) $filters['riskLevel']));
        }

        return array_map(fn(array $row): ValidationReview => $this->hydrate($row), $qb->executeQuery()->fetchAllAssociative());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ValidationReview
    {
        return new ValidationReview(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: (string) $row['organization_uuid'],
            assessmentUuid: (string) $row['assessment_uuid'],
            assessmentVersion: (string) $row['assessment_version'],
            reviewerUserId: (int) $row['reviewer_user_id'],
            reviewerName: (string) $row['reviewer_name'],
            reviewerCredentials: $row['reviewer_credentials'] ?? null,
            reviewRound: (int) ($row['review_round'] ?? 1),
            score: $this->decode($row['score_json'] ?? null),
            riskLevel: (string) $row['risk_level'],
            bodyRegions: array_values($this->decode($row['body_regions_json'] ?? null)),
            riskFactors: array_values($this->decode($row['risk_factors_json'] ?? null)),
            notes: $row['notes'] ?? null,
            isPrimary: (bool) ($row['is_primary'] ?? false),
            isFinal: (bool) ($row['is_final'] ?? true),
            submittedAt: isset($row['submitted_at']) ? (string) $row['submitted_at'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    /** @param array<string, mixed>|list<string> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
