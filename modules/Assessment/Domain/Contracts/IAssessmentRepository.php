<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Domain\Contracts;

use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\ComparisonReport;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;

interface IAssessmentRepository
{
    public function create(Assessment $assessment): int;

    public function update(Assessment $assessment): void;

    public function addVideo(AssessmentVideo $video): int;

    public function updateVideoProcessing(AssessmentVideo $video): void;

    /**
     * @param array<string, mixed> $result
     */
    public function saveVideoProcessingResult(array $result): void;

    /**
     * @return array<string, mixed>|null
     */
    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array;

    public function saveAiScoreOutput(AiScoreOutput $output): int;

    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput;

    public function findByUuid(string $uuid): ?Assessment;

    public function findById(int $id): ?Assessment;

    /**
     * @return list<Assessment>
     */
    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array;

    public function createComparisonReport(ComparisonReport $report): int;

    public function updateComparisonReport(ComparisonReport $report): void;

    public function findComparisonReportByUuid(string $uuid): ?ComparisonReport;

    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?ComparisonReport;

    /**
     * @return list<ComparisonReport>
     */
    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array;
}
