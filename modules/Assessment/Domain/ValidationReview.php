<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class ValidationReview
{
    /**
     * @param array<string, mixed> $score
     * @param list<string> $bodyRegions
     * @param list<string> $riskFactors
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $assessmentUuid,
        public readonly string $assessmentVersion,
        public readonly int $reviewerUserId,
        public readonly string $reviewerName,
        public readonly ?string $reviewerCredentials,
        public readonly int $reviewRound,
        public readonly array $score,
        public readonly string $riskLevel,
        public readonly array $bodyRegions,
        public readonly array $riskFactors,
        public readonly ?string $notes,
        public readonly bool $isPrimary = false,
        public readonly bool $isFinal = true,
        public readonly ?string $submittedAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
        if (trim($this->assessmentUuid) === '') {
            throw new ValidationException(['assessmentUuid' => 'Assessment is required.']);
        }
        if (trim($this->assessmentVersion) === '') {
            throw new ValidationException(['assessmentVersion' => 'Assessment version is required.']);
        }
        if ($this->reviewRound < 1) {
            throw new ValidationException(['reviewRound' => 'Review round must be at least 1.']);
        }
        if (trim($this->reviewerName) === '') {
            throw new ValidationException(['reviewerName' => 'Reviewer name is required.']);
        }
        if (trim($this->riskLevel) === '') {
            throw new ValidationException(['riskLevel' => 'Risk level is required.']);
        }
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'organizationUuid' => $this->organizationUuid,
            'assessmentUuid' => $this->assessmentUuid,
            'assessmentVersion' => $this->assessmentVersion,
            'reviewerUserId' => $this->reviewerUserId,
            'reviewerName' => $this->reviewerName,
            'reviewerCredentials' => $this->reviewerCredentials,
            'reviewRound' => $this->reviewRound,
            'score' => $this->score,
            'riskLevel' => $this->riskLevel,
            'bodyRegions' => $this->bodyRegions,
            'riskFactors' => $this->riskFactors,
            'notes' => $this->notes,
            'isPrimary' => $this->isPrimary,
            'isFinal' => $this->isFinal,
            'submittedAt' => $this->submittedAt,
        ];
    }
}
