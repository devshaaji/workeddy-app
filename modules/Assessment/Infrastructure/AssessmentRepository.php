<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\AiScoreOutput;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\ComparisonReport;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Platform\Clock\IClock;

final class AssessmentRepository implements IAssessmentRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(Assessment $assessment): int
    {
        $now = $this->now();
        $this->connection->insert('assessments', [
            'uuid' => $assessment->getUuid(),
            'organization_id' => $assessment->getOrganizationId(),
            'organization_uuid' => $assessment->getOrganizationUuid(),
            'task_id' => $assessment->getTaskId(),
            'task_uuid' => $assessment->getTaskUuid(),
            'model' => $assessment->getModel(),
            'metrics_json' => $this->encode($assessment->getMetrics()),
            'initial_score_json' => $this->encode($assessment->getInitialScoreData()),
            'final_score_json' => $this->encode($assessment->getFinalScoreData()),
            'status' => $assessment->getStatus(),
            'is_baseline' => $assessment->isBaseline() ? 1 : 0,
            'score_source' => $assessment->getScoreSource(),
            'created_by' => $assessment->getCreatedBy(),
            'reviewer_id' => $assessment->getReviewerId(),
            'reviewer_name' => $assessment->getReviewerName(),
            'reviewer_credentials' => $assessment->getReviewerCredentials(),
            'reviewer_notes' => $assessment->getReviewerNotes(),
            'adjustment_reason' => $assessment->getAdjustmentReason(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $id = (int) $this->connection->lastInsertId();
        $this->replaceEvidence($id, $assessment);

        return $id;
    }

    public function update(Assessment $assessment): void
    {
        $this->connection->update('assessments', [
            'metrics_json' => $this->encode($assessment->getMetrics()),
            'initial_score_json' => $this->encode($assessment->getInitialScoreData()),
            'final_score_json' => $this->encode($assessment->getFinalScoreData()),
            'status' => $assessment->getStatus(),
            'is_baseline' => $assessment->isBaseline() ? 1 : 0,
            'score_source' => $assessment->getScoreSource(),
            'reviewer_id' => $assessment->getReviewerId(),
            'reviewer_name' => $assessment->getReviewerName(),
            'reviewer_credentials' => $assessment->getReviewerCredentials(),
            'reviewer_notes' => $assessment->getReviewerNotes(),
            'adjustment_reason' => $assessment->getAdjustmentReason(),
            'updated_at' => $this->now(),
        ], ['uuid' => $assessment->getUuid()]);

        if ($assessment->getId() !== null) {
            $this->replaceEvidence((int) $assessment->getId(), $assessment);
        }
    }

    public function addVideo(AssessmentVideo $video): int
    {
        $this->connection->insert('assessment_videos', [
            'uuid' => $video->getUuid(),
            'assessment_id' => $video->getAssessmentId(),
            'storage_file_uuid' => $video->getStorageFileUuid(),
            'original_filename' => $video->getOriginalFilename(),
            'mime_type' => $video->getMimeType(),
            'size_bytes' => $video->getSizeBytes(),
            'duration_seconds' => $video->getDurationSeconds(),
            'consent_text_version' => $video->getConsentTextVersion(),
            'face_blur_requested' => $video->isFaceBlurRequested() ? 1 : 0,
            'processing_status' => $video->getProcessingStatus(),
            'processing_started_at' => $video->getProcessingStartedAt(),
            'processing_completed_at' => $video->getProcessingCompletedAt(),
            'processing_error' => $video->getProcessingError(),
            'thumbnail_storage_file_uuid' => $video->getThumbnailStorageFileUuid(),
            'pose_video_storage_file_uuid' => $video->getPoseVideoStorageFileUuid(),
            'blurred_storage_file_uuid' => $video->getBlurredStorageFileUuid(),
            'faces_blurred' => $video->areFacesBlurred() ? 1 : 0,
            'processing_confidence' => $video->getProcessingConfidence(),
            'created_at' => $this->now(),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateVideoProcessing(AssessmentVideo $video): void
    {
        $this->connection->update('assessment_videos', [
            'processing_status' => $video->getProcessingStatus(),
            'processing_started_at' => $video->getProcessingStartedAt(),
            'processing_completed_at' => $video->getProcessingCompletedAt(),
            'processing_error' => $video->getProcessingError(),
            'thumbnail_storage_file_uuid' => $video->getThumbnailStorageFileUuid(),
            'pose_video_storage_file_uuid' => $video->getPoseVideoStorageFileUuid(),
            'blurred_storage_file_uuid' => $video->getBlurredStorageFileUuid(),
            'faces_blurred' => $video->areFacesBlurred() ? 1 : 0,
            'processing_confidence' => $video->getProcessingConfidence(),
        ], ['uuid' => $video->getUuid()]);
    }

    public function saveVideoProcessingResult(array $result): void
    {
        $now = $this->now();
        $this->connection->insert('assessment_video_processing_results', [
            'assessment_uuid' => (string) $result['assessmentUuid'],
            'assessment_video_uuid' => (string) $result['assessmentVideoUuid'],
            'video_sha256' => (string) $result['videoSha256'],
            'processing_profile_hash' => (string) $result['processingProfileHash'],
            'metrics_json' => $this->encode($result['metrics'] ?? []),
            'timeline_json' => $this->encode($result['timeline'] ?? []),
            'risky_windows_json' => $this->encode($result['riskyWindows'] ?? []),
            'pose_video_storage_file_uuid' => $result['poseVideoStorageFileUuid'] ?? null,
            'thumbnail_storage_file_uuid' => $result['thumbnailStorageFileUuid'] ?? null,
            'blurred_storage_file_uuid' => $result['blurredVideoStorageFileUuid'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findReusableVideoProcessingResult(string $videoSha256, string $processingProfileHash): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM assessment_video_processing_results WHERE video_sha256 = ? AND processing_profile_hash = ? ORDER BY id DESC LIMIT 1',
            [$videoSha256, $processingProfileHash],
        );
        if ($row === false) {
            return null;
        }

        return [
            'assessmentUuid' => (string) $row['assessment_uuid'],
            'assessmentVideoUuid' => (string) $row['assessment_video_uuid'],
            'videoSha256' => (string) $row['video_sha256'],
            'processingProfileHash' => (string) $row['processing_profile_hash'],
            'metrics' => $this->decode($row['metrics_json'] ?? null),
            'timeline' => $this->decode($row['timeline_json'] ?? null),
            'riskyWindows' => $this->decode($row['risky_windows_json'] ?? null),
            'poseVideoStorageFileUuid' => $row['pose_video_storage_file_uuid'] ?? null,
            'thumbnailStorageFileUuid' => $row['thumbnail_storage_file_uuid'] ?? null,
            'blurredVideoStorageFileUuid' => $row['blurred_storage_file_uuid'] ?? null,
        ];
    }

    public function saveAiScoreOutput(AiScoreOutput $output): int
    {
        $now = $this->now();
        $this->connection->insert('ai_score_outputs', [
            'uuid' => $output->uuid,
            'assessment_uuid' => $output->assessmentUuid,
            'assessment_video_uuid' => $output->assessmentVideoUuid,
            'score_model' => $output->scoreModel,
            'score_source' => $output->scoreSource,
            'model_version' => $output->modelVersion,
            'confidence' => $output->confidence,
            'metrics_json' => $this->encode($output->metrics),
            'score_json' => $this->encode($output->score),
            'timeline_json' => json_encode($output->timeline, JSON_THROW_ON_ERROR),
            'flags_json' => $this->encode($output->flags),
            'metadata_json' => $this->encode($output->metadata),
            'created_by_worker' => $output->createdByWorker,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findLatestAiScoreOutput(string $assessmentUuid): ?AiScoreOutput
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ai_score_outputs WHERE assessment_uuid = ? ORDER BY id DESC LIMIT 1',
            [$assessmentUuid],
        );

        return $row === false ? null : $this->hydrateAiScoreOutput($row);
    }

    public function findByUuid(string $uuid): ?Assessment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM assessments WHERE uuid = ? AND deleted_at IS NULL', [$uuid]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?Assessment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM assessments WHERE id = ? AND deleted_at IS NULL', [$id]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllByOrganizationId(?int $organizationId, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('assessments')
            ->where('deleted_at IS NULL');

        if ($organizationId !== null) {
            $qb->andWhere('organization_id = :organizationId')
                ->setParameter('organizationId', $organizationId);
        }

        $rows = $qb->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): Assessment => $this->hydrate($row), $rows);
    }

    public function createComparisonReport(ComparisonReport $report): int
    {
        $now = $this->now();
        $this->connection->insert('comparison_reports', [
            'uuid' => $report->uuid,
            'organization_id' => $report->organizationId,
            'organization_uuid' => $report->organizationUuid,
            'baseline_assessment_uuid' => $report->baselineAssessmentUuid,
            'follow_up_assessment_uuid' => $report->followUpAssessmentUuid,
            'corrective_action_uuid' => $report->correctiveActionUuid,
            'model' => $report->model,
            'baseline_score_json' => $this->encode($report->baselineScore),
            'follow_up_score_json' => $this->encode($report->followUpScore),
            'score_diff_json' => $this->encode($report->scoreDiff),
            'risk_reduction_percent' => $report->riskReductionPercent,
            'direction' => $report->direction,
            'body_regions_improved_json' => $this->encode($report->bodyRegionsImproved),
            'body_regions_worsened_json' => $this->encode($report->bodyRegionsWorsened),
            'evidence_chain_json' => $this->encode($report->evidenceChain),
            'status' => $report->status,
            'generated_by' => $report->generatedBy,
            'generated_at' => $report->generatedAt ?? $now,
            'locked_at' => $report->lockedAt,
            'created_at' => $report->createdAt ?? $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateComparisonReport(ComparisonReport $report): void
    {
        $this->connection->update('comparison_reports', [
            'corrective_action_uuid' => $report->correctiveActionUuid,
            'baseline_score_json' => $this->encode($report->baselineScore),
            'follow_up_score_json' => $this->encode($report->followUpScore),
            'score_diff_json' => $this->encode($report->scoreDiff),
            'risk_reduction_percent' => $report->riskReductionPercent,
            'direction' => $report->direction,
            'body_regions_improved_json' => $this->encode($report->bodyRegionsImproved),
            'body_regions_worsened_json' => $this->encode($report->bodyRegionsWorsened),
            'evidence_chain_json' => $this->encode($report->evidenceChain),
            'status' => $report->status,
            'locked_at' => $report->lockedAt,
            'updated_at' => $this->now(),
        ], ['uuid' => $report->uuid]);
    }

    public function findComparisonReportByUuid(string $uuid): ?ComparisonReport
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM comparison_reports WHERE uuid = ?', [$uuid]);

        return $row === false ? null : $this->hydrateComparisonReport($row);
    }

    public function findComparisonReportByBaselineAndFollowUp(string $baselineAssessmentUuid, string $followUpAssessmentUuid): ?ComparisonReport
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM comparison_reports WHERE baseline_assessment_uuid = ? AND follow_up_assessment_uuid = ?',
            [$baselineAssessmentUuid, $followUpAssessmentUuid],
        );

        return $row === false ? null : $this->hydrateComparisonReport($row);
    }

    public function findComparisonReportsByOrganizationId(int $organizationId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('comparison_reports')
            ->where('organization_id = :organizationId')
            ->setParameter('organizationId', $organizationId)
            ->orderBy('generated_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        foreach ([
            'baselineAssessmentUuid' => 'baseline_assessment_uuid',
            'followUpAssessmentUuid' => 'follow_up_assessment_uuid',
            'correctiveActionUuid' => 'corrective_action_uuid',
            'status' => 'status',
        ] as $filterKey => $column) {
            if (!isset($filters[$filterKey]) || $filters[$filterKey] === '') {
                continue;
            }
            $qb->andWhere($column . ' = :' . $filterKey)->setParameter($filterKey, (string) $filters[$filterKey]);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn(array $row): ComparisonReport => $this->hydrateComparisonReport($row), $rows);
    }

    private function replaceEvidence(int $assessmentId, Assessment $assessment): void
    {
        $this->connection->delete('assessment_risk_factors', ['assessment_id' => $assessmentId]);
        foreach ($assessment->getRiskFactors() as $factor) {
            $this->connection->insert('assessment_risk_factors', [
                'assessment_id' => $assessmentId,
                'factor_key' => $factor,
                'created_at' => $this->now(),
            ]);
        }

        $this->connection->delete('assessment_body_regions', ['assessment_id' => $assessmentId]);
        foreach ($assessment->getBodyRegions() as $region) {
            $this->connection->insert('assessment_body_regions', [
                'assessment_id' => $assessmentId,
                'region' => (string) ($region['region'] ?? ''),
                'side' => (string) ($region['side'] ?? ''),
                'intensity' => (int) ($region['intensity'] ?? 0),
                'created_at' => $this->now(),
            ]);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrateComparisonReport(array $row): ComparisonReport
    {
        return new ComparisonReport(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: (string) $row['organization_uuid'],
            baselineAssessmentUuid: (string) $row['baseline_assessment_uuid'],
            followUpAssessmentUuid: (string) $row['follow_up_assessment_uuid'],
            correctiveActionUuid: $row['corrective_action_uuid'] ?? null,
            model: (string) $row['model'],
            baselineScore: $this->decode($row['baseline_score_json'] ?? null),
            followUpScore: $this->decode($row['follow_up_score_json'] ?? null),
            scoreDiff: $this->decode($row['score_diff_json'] ?? null),
            riskReductionPercent: (float) $row['risk_reduction_percent'],
            direction: (string) $row['direction'],
            bodyRegionsImproved: array_values($this->decode($row['body_regions_improved_json'] ?? null)),
            bodyRegionsWorsened: array_values($this->decode($row['body_regions_worsened_json'] ?? null)),
            evidenceChain: $this->decode($row['evidence_chain_json'] ?? null),
            status: (string) $row['status'],
            generatedBy: (int) $row['generated_by'],
            generatedAt: isset($row['generated_at']) ? (string) $row['generated_at'] : null,
            lockedAt: isset($row['locked_at']) ? (string) $row['locked_at'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateAiScoreOutput(array $row): AiScoreOutput
    {
        $timeline = $row['timeline_json'] ?? null;
        $decodedTimeline = $timeline === null || $timeline === ''
            ? []
            : json_decode((string) $timeline, true, 512, JSON_THROW_ON_ERROR);

        return new AiScoreOutput(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            assessmentUuid: (string) $row['assessment_uuid'],
            assessmentVideoUuid: isset($row['assessment_video_uuid']) ? (string) $row['assessment_video_uuid'] : null,
            scoreModel: (string) $row['score_model'],
            scoreSource: (string) $row['score_source'],
            modelVersion: (string) $row['model_version'],
            confidence: isset($row['confidence']) ? (float) $row['confidence'] : null,
            metrics: $this->decode($row['metrics_json'] ?? null),
            score: $this->decode($row['score_json'] ?? null),
            timeline: is_array($decodedTimeline) ? array_values($decodedTimeline) : [],
            flags: $this->decode($row['flags_json'] ?? null),
            metadata: $this->decode($row['metadata_json'] ?? null),
            createdByWorker: isset($row['created_by_worker']) ? (string) $row['created_by_worker'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Assessment
    {
        $id = (int) $row['id'];

        return Assessment::reconstitute(
            id: $id,
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: (string) $row['organization_uuid'],
            taskId: (int) $row['task_id'],
            taskUuid: (string) $row['task_uuid'],
            model: (string) $row['model'],
            metrics: $this->decode($row['metrics_json'] ?? null),
            initialScore: $this->decode($row['initial_score_json'] ?? null),
            riskFactors: $this->loadRiskFactors($id),
            bodyRegions: $this->loadBodyRegions($id),
            createdBy: (int) $row['created_by'],
            status: (string) $row['status'],
            scoreSource: (string) $row['score_source'],
            finalScore: $this->decodeNullable($row['final_score_json'] ?? null),
            reviewerId: isset($row['reviewer_id']) ? (int) $row['reviewer_id'] : null,
            reviewerName: $row['reviewer_name'] ?? null,
            reviewerCredentials: $row['reviewer_credentials'] ?? null,
            reviewerNotes: $row['reviewer_notes'] ?? null,
            adjustmentReason: $row['adjustment_reason'] ?? null,
            isBaseline: (bool) ($row['is_baseline'] ?? false),
            videos: $this->loadVideos($id),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    /** @return list<string> */
    private function loadRiskFactors(int $assessmentId): array
    {
        return array_map(
            static fn(array $row): string => (string) $row['factor_key'],
            $this->connection->fetchAllAssociative('SELECT factor_key FROM assessment_risk_factors WHERE assessment_id = ? ORDER BY id ASC', [$assessmentId]),
        );
    }

    /** @return list<array<string, mixed>> */
    private function loadBodyRegions(int $assessmentId): array
    {
        return array_map(
            static fn(array $row): array => [
                'region' => (string) $row['region'],
                'side' => (string) $row['side'],
                'intensity' => (int) $row['intensity'],
            ],
            $this->connection->fetchAllAssociative('SELECT region, side, intensity FROM assessment_body_regions WHERE assessment_id = ? ORDER BY id ASC', [$assessmentId]),
        );
    }

    /** @return list<AssessmentVideo> */
    private function loadVideos(int $assessmentId): array
    {
        return array_map(
            static fn(array $row): AssessmentVideo => new AssessmentVideo(
                id: (int) $row['id'],
                uuid: (string) $row['uuid'],
                assessmentId: (int) $row['assessment_id'],
                storageFileUuid: (string) $row['storage_file_uuid'],
                originalFilename: (string) $row['original_filename'],
                mimeType: (string) $row['mime_type'],
                sizeBytes: (int) $row['size_bytes'],
                durationSeconds: (int) $row['duration_seconds'],
                consentTextVersion: (string) $row['consent_text_version'],
                faceBlurRequested: (bool) $row['face_blur_requested'],
                processingStatus: (string) $row['processing_status'],
                processingStartedAt: isset($row['processing_started_at']) ? (string) $row['processing_started_at'] : null,
                processingCompletedAt: isset($row['processing_completed_at']) ? (string) $row['processing_completed_at'] : null,
                processingError: $row['processing_error'] ?? null,
                thumbnailStorageFileUuid: $row['thumbnail_storage_file_uuid'] ?? null,
                poseVideoStorageFileUuid: $row['pose_video_storage_file_uuid'] ?? null,
                facesBlurred: (bool) ($row['faces_blurred'] ?? false),
                processingConfidence: isset($row['processing_confidence']) ? (float) $row['processing_confidence'] : null,
                createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
                blurredStorageFileUuid: $row['blurred_storage_file_uuid'] ?? null,
            ),
            $this->connection->fetchAllAssociative('SELECT * FROM assessment_videos WHERE assessment_id = ? ORDER BY id ASC', [$assessmentId]),
        );
    }

    /**
     * @param array<string, mixed>|null $value
     */
    private function encode(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        return $value === null || $value === '' ? [] : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed>|null */
    private function decodeNullable(mixed $value): ?array
    {
        return $value === null || $value === '' ? null : $this->decode($value);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
