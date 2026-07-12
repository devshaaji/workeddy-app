<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class ComparisonReport
{
    public const STATUSES = ['draft', 'generated', 'locked'];

    /**
     * @param array<string, mixed> $baselineScore
     * @param array<string, mixed> $followUpScore
     * @param array<string, mixed> $scoreDiff
     * @param list<array<string, mixed>> $bodyRegionsImproved
     * @param list<array<string, mixed>> $bodyRegionsWorsened
     * @param array<string, mixed> $evidenceChain
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $baselineAssessmentUuid,
        public readonly string $followUpAssessmentUuid,
        public readonly ?string $correctiveActionUuid,
        public readonly string $model,
        public readonly array $baselineScore,
        public readonly array $followUpScore,
        public readonly array $scoreDiff,
        public readonly float $riskReductionPercent,
        public readonly string $direction,
        public readonly array $bodyRegionsImproved,
        public readonly array $bodyRegionsWorsened,
        public readonly array $evidenceChain,
        public readonly string $status,
        public readonly int $generatedBy,
        public readonly ?string $generatedAt = null,
        public readonly ?string $lockedAt = null,
        public readonly ?string $createdAt = null,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new ValidationException(['status' => 'Invalid comparison report status.']);
        }
    }

    public function lock(): self
    {
        if ($this->status === 'locked') {
            return $this;
        }

        return new self(
            id: $this->id,
            uuid: $this->uuid,
            organizationId: $this->organizationId,
            organizationUuid: $this->organizationUuid,
            baselineAssessmentUuid: $this->baselineAssessmentUuid,
            followUpAssessmentUuid: $this->followUpAssessmentUuid,
            correctiveActionUuid: $this->correctiveActionUuid,
            model: $this->model,
            baselineScore: $this->baselineScore,
            followUpScore: $this->followUpScore,
            scoreDiff: $this->scoreDiff,
            riskReductionPercent: $this->riskReductionPercent,
            direction: $this->direction,
            bodyRegionsImproved: $this->bodyRegionsImproved,
            bodyRegionsWorsened: $this->bodyRegionsWorsened,
            evidenceChain: $this->evidenceChain,
            status: 'locked',
            generatedBy: $this->generatedBy,
            generatedAt: $this->generatedAt,
            lockedAt: date('Y-m-d H:i:s'),
            createdAt: $this->createdAt,
        );
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'organizationUuid' => $this->organizationUuid,
            'baselineAssessmentUuid' => $this->baselineAssessmentUuid,
            'followUpAssessmentUuid' => $this->followUpAssessmentUuid,
            'correctiveActionUuid' => $this->correctiveActionUuid,
            'model' => $this->model,
            'baselineScore' => $this->baselineScore,
            'followUpScore' => $this->followUpScore,
            'scoreDiff' => $this->scoreDiff,
            'riskReductionPercent' => $this->riskReductionPercent,
            'direction' => $this->direction,
            'bodyRegionsImproved' => $this->bodyRegionsImproved,
            'bodyRegionsWorsened' => $this->bodyRegionsWorsened,
            'evidenceChain' => $this->evidenceChain,
            'status' => $this->status,
            'generatedBy' => $this->generatedBy,
            'generatedAt' => $this->generatedAt,
            'lockedAt' => $this->lockedAt,
            'createdAt' => $this->createdAt,
        ];
    }
}
