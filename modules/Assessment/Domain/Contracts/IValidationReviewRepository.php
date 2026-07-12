<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Domain\Contracts;

use WorkEddy\Modules\Assessment\Domain\ValidationReview;

interface IValidationReviewRepository
{
    public function create(ValidationReview $review): int;

    public function findByUuid(string $uuid): ?ValidationReview;

    /**
     * @return list<ValidationReview>
     */
    public function findByAssessmentUuid(string $assessmentUuid, bool $finalOnly = false): array;

    /**
     * @param array<string, mixed> $filters
     * @return list<ValidationReview>
     */
    public function findByOrganizationId(int $organizationId, array $filters = [], bool $finalOnly = false, int $limit = 500, int $offset = 0): array;
}
