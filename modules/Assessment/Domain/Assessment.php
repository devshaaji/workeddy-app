<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class Assessment
{
    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $initialScore
     * @param list<string> $riskFactors
     * @param list<array<string, mixed>> $bodyRegions
     * @param array<string, mixed>|null $finalScore
     * @param list<AssessmentVideo> $videos
     */
    private function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly int $organizationId,
        private readonly string $organizationUuid,
        private readonly int $taskId,
        private readonly string $taskUuid,
        private readonly string $model,
        private readonly array $metrics,
        private readonly array $initialScore,
        private readonly array $riskFactors,
        private readonly array $bodyRegions,
        private readonly int $createdBy,
        private readonly string $status = 'draft',
        private readonly string $scoreSource = 'manual',
        private readonly ?array $finalScore = null,
        private readonly ?int $reviewerId = null,
        private readonly ?string $reviewerName = null,
        private readonly ?string $reviewerCredentials = null,
        private readonly ?string $reviewerNotes = null,
        private readonly ?string $adjustmentReason = null,
        private readonly bool $isBaseline = false,
        private readonly array $videos = [],
        private readonly ?string $createdAt = null,
    ) {}

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $initialScore
     * @param list<string> $riskFactors
     * @param list<array<string, mixed>> $bodyRegions
     */
    public static function create(
        ?int $id,
        string $uuid,
        int $organizationId,
        string $organizationUuid,
        int $taskId,
        string $taskUuid,
        string $model,
        array $metrics,
        array $initialScore,
        array $riskFactors,
        array $bodyRegions,
        int $createdBy,
        ?string $createdAt = null,
    ): self {
        return new self($id, $uuid, $organizationId, $organizationUuid, $taskId, $taskUuid, strtolower($model), $metrics, $initialScore, array_values($riskFactors), array_values($bodyRegions), $createdBy, createdAt: $createdAt);
    }

    /**
     * @param array<string, mixed>|null $finalScore
     * @param list<string> $riskFactors
     * @param list<array<string, mixed>> $bodyRegions
     * @param list<AssessmentVideo> $videos
     */
    public static function reconstitute(?int $id, string $uuid, int $organizationId, string $organizationUuid, int $taskId, string $taskUuid, string $model, array $metrics, array $initialScore, array $riskFactors, array $bodyRegions, int $createdBy, string $status, string $scoreSource, ?array $finalScore, ?int $reviewerId, ?string $reviewerName, ?string $reviewerCredentials, ?string $reviewerNotes, ?string $adjustmentReason, bool $isBaseline = false, array $videos = [], ?string $createdAt = null): self
    {
        return new self($id, $uuid, $organizationId, $organizationUuid, $taskId, $taskUuid, $model, $metrics, $initialScore, $riskFactors, $bodyRegions, $createdBy, $status, $scoreSource, $finalScore, $reviewerId, $reviewerName, $reviewerCredentials, $reviewerNotes, $adjustmentReason, $isBaseline, $videos, $createdAt);
    }

    public function withId(int $id): self
    {
        return self::reconstitute($id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $this->metrics, $this->initialScore, $this->riskFactors, $this->bodyRegions, $this->createdBy, $this->status, $this->scoreSource, $this->finalScore, $this->reviewerId, $this->reviewerName, $this->reviewerCredentials, $this->reviewerNotes, $this->adjustmentReason, $this->isBaseline, $this->videos, $this->createdAt);
    }

    public function markSubmitted(): self
    {
        $this->assertMutable();
        return self::reconstitute($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $this->metrics, $this->initialScore, $this->riskFactors, $this->bodyRegions, $this->createdBy, 'pending_review', $this->scoreSource, $this->finalScore, $this->reviewerId, $this->reviewerName, $this->reviewerCredentials, $this->reviewerNotes, $this->adjustmentReason, $this->isBaseline, $this->videos, $this->createdAt);
    }

    /**
     * @param array<string, mixed> $finalScore
     */
    public function markReviewed(int $reviewerId, string $reviewerName, ?string $reviewerCredentials, ?string $reviewerNotes, array $finalScore, ?string $adjustmentReason, bool $lock): self
    {
        if ($this->status !== 'pending_review') {
            throw new ValidationException(['status' => 'Only pending assessments can be reviewed.']);
        }

        return self::reconstitute($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $this->metrics, $this->initialScore, $this->riskFactors, $this->bodyRegions, $this->createdBy, $lock ? 'locked' : 'reviewed', 'reviewer_confirmed', $finalScore, $reviewerId, $reviewerName, $reviewerCredentials, $reviewerNotes, $adjustmentReason, $this->isBaseline, $this->videos, $this->createdAt);
    }

    public function markFlagged(int $reviewerId, string $reviewerName, ?string $reviewerCredentials, string $reviewerNotes): self
    {
        if ($this->status !== 'pending_review') {
            throw new ValidationException(['status' => 'Only pending assessments can be flagged.']);
        }

        $notes = trim($reviewerNotes);
        if ($notes === '') {
            throw new ValidationException(['reviewerNotes' => 'Flagged assessments require reviewer notes.']);
        }

        return self::reconstitute($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $this->metrics, $this->initialScore, $this->riskFactors, $this->bodyRegions, $this->createdBy, 'flagged', $this->scoreSource, $this->finalScore, $reviewerId, $reviewerName, $reviewerCredentials, $notes, $this->adjustmentReason, $this->isBaseline, $this->videos, $this->createdAt);
    }

    public function markBaseline(): self
    {
        if (!in_array($this->status, ['reviewed', 'locked'], true)) {
            throw new ValidationException(['status' => 'Only reviewed or locked assessments can become baseline.']);
        }

        return self::reconstitute($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $this->metrics, $this->initialScore, $this->riskFactors, $this->bodyRegions, $this->createdBy, 'locked', $this->scoreSource, $this->finalScore, $this->reviewerId, $this->reviewerName, $this->reviewerCredentials, $this->reviewerNotes, $this->adjustmentReason, true, $this->videos, $this->createdAt);
    }

    public function withVideo(AssessmentVideo $video): self
    {
        $this->assertMutable();
        $videos = $this->videos;
        $videos[] = $video;

        return self::reconstitute($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $this->metrics, $this->initialScore, $this->riskFactors, $this->bodyRegions, $this->createdBy, $this->status, $this->scoreSource, $this->finalScore, $this->reviewerId, $this->reviewerName, $this->reviewerCredentials, $this->reviewerNotes, $this->adjustmentReason, $this->isBaseline, $videos, $this->createdAt);
    }

    public function replaceVideo(AssessmentVideo $video): self
    {
        $videos = [];
        $replaced = false;
        foreach ($this->videos as $existing) {
            if ($existing->getUuid() === $video->getUuid()) {
                $videos[] = $video;
                $replaced = true;
                continue;
            }
            $videos[] = $existing;
        }

        if (!$replaced) {
            $videos[] = $video;
        }

        return self::reconstitute($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $this->metrics, $this->initialScore, $this->riskFactors, $this->bodyRegions, $this->createdBy, $this->status, $this->scoreSource, $this->finalScore, $this->reviewerId, $this->reviewerName, $this->reviewerCredentials, $this->reviewerNotes, $this->adjustmentReason, $this->isBaseline, $videos, $this->createdAt);
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $score
     */
    public function withAiEstimatedScore(array $metrics, array $score): self
    {
        return self::reconstitute($this->id, $this->uuid, $this->organizationId, $this->organizationUuid, $this->taskId, $this->taskUuid, $this->model, $metrics, $score, $this->riskFactors, $this->bodyRegions, $this->createdBy, $this->status, $this->scoreSource, $this->finalScore, $this->reviewerId, $this->reviewerName, $this->reviewerCredentials, $this->reviewerNotes, $this->adjustmentReason, $this->isBaseline, $this->videos, $this->createdAt);
    }

    public function assertMutable(): void
    {
        if (!$this->isMutable()) {
            throw new ValidationException(['status' => 'Locked assessments cannot be changed.']);
        }
    }

    public function isMutable(): bool
    {
        return $this->status !== 'locked';
    }

    public function getId(): ?int { return $this->id; }
    public function getUuid(): string { return $this->uuid; }
    public function getOrganizationId(): int { return $this->organizationId; }
    public function getOrganizationUuid(): string { return $this->organizationUuid; }
    public function getTaskId(): int { return $this->taskId; }
    public function getTaskUuid(): string { return $this->taskUuid; }
    public function getStatus(): string { return $this->status; }
    public function getModel(): string { return $this->model; }
    public function getCreatedBy(): int { return $this->createdBy; }
    public function getScoreSource(): string { return $this->scoreSource; }
    public function getReviewerId(): ?int { return $this->reviewerId; }
    public function getReviewerName(): ?string { return $this->reviewerName; }
    public function getReviewerCredentials(): ?string { return $this->reviewerCredentials; }
    public function getReviewerNotes(): ?string { return $this->reviewerNotes; }
    public function getAdjustmentReason(): ?string { return $this->adjustmentReason; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function isBaseline(): bool { return $this->isBaseline; }

    /** @return array<string, mixed> */
    public function getMetrics(): array { return $this->metrics; }

    /** @return array<string, mixed> */
    public function getInitialScoreData(): array { return $this->initialScore; }

    /** @return array<string, mixed>|null */
    public function getFinalScoreData(): ?array { return $this->finalScore; }

    /** @return list<string> */
    public function getRiskFactors(): array { return $this->riskFactors; }

    /** @return list<array<string, mixed>> */
    public function getBodyRegions(): array { return $this->bodyRegions; }

    /** @return list<AssessmentVideo> */
    public function getVideos(): array { return $this->videos; }

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        $final = $this->finalScore ?? $this->initialScore;

        return [
            'uuid' => $this->uuid,
            'organizationUuid' => $this->organizationUuid,
            'taskUuid' => $this->taskUuid,
            'model' => $this->model,
            'status' => $this->status,
            'isBaseline' => $this->isBaseline,
            'isLocked' => !$this->isMutable(),
            'scoreSource' => $this->scoreSource,
            'initialScore' => $this->formatScore($this->initialScore),
            'finalScore' => $this->formatScore($final),
            'metrics' => $this->metrics,
            'riskFactors' => $this->riskFactors,
            'bodyRegions' => $this->bodyRegions,
            'bodyRegionHeatmap' => $this->bodyRegionHeatmap(),
            'videos' => array_map(static fn(AssessmentVideo $video): array => $video->toView(), $this->videos),
            'review' => [
                'reviewerId' => $this->reviewerId,
                'reviewerName' => $this->reviewerName,
                'reviewerCredentials' => $this->reviewerCredentials,
                'reviewerNotes' => $this->reviewerNotes,
                'adjustmentReason' => $this->adjustmentReason,
            ],
            'createdAt' => $this->createdAt,
        ];
    }

    /**
     * @param array<string, mixed> $score
     * @return array<string, mixed>
     */
    private function formatScore(array $score): array
    {
        return [
            'raw' => isset($score['raw_score']) ? (float) $score['raw_score'] : (float) ($score['score'] ?? 0),
            'normalized' => isset($score['normalized_score']) ? (float) $score['normalized_score'] : null,
            'riskLevel' => $score['risk_level'] ?? null,
            'riskCategory' => $score['risk_category'] ?? null,
            'algorithmVersion' => $score['algorithm_version'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function bodyRegionHeatmap(): array
    {
        return [
            'frontSvg' => $this->renderHeatmapSvg('front'),
            'backSvg' => $this->renderHeatmapSvg('back'),
        ];
    }

    private function renderHeatmapSvg(string $side): string
    {
        $regions = array_values(array_filter(
            $this->bodyRegions,
            static fn(array $region): bool => strtolower((string) ($region['side'] ?? 'front')) === $side,
        ));
        $markers = '';
        foreach ($regions as $index => $region) {
            $intensity = max(0, min(5, (int) ($region['intensity'] ?? 0)));
            $fill = ['#e8f5e9', '#c8e6c9', '#fff59d', '#ffcc80', '#ef9a9a', '#b71c1c'][$intensity];
            $rawLabel = (string) ($region['region'] ?? 'region');
            $label = htmlspecialchars($rawLabel, ENT_QUOTES, 'UTF-8');
            [$x, $y] = $this->regionCoordinates($rawLabel, $side, $index);
            $radius = 3 + ($intensity * 1.2);
            $markers .= '<g>';
            $markers .= '<circle data-region="' . $label . '" cx="' . $x . '" cy="' . $y . '" r="' . $radius . '" fill="' . $fill . '" stroke="#1f2937" stroke-width="1.25" />';
            $markers .= '<text x="' . $x . '" y="' . $y . '" font-size="5.5" font-weight="700" text-anchor="middle" dominant-baseline="central" fill="#111827" style="pointer-events:none;">' . $intensity . '</text>';
            $markers .= '<title>' . $label . ': ' . $intensity . '</title>';
            $markers .= '</g>';
        }

        return '<svg viewBox="0 0 100 180" role="img" aria-label="' . $side . ' body region heat map" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="180" rx="12" fill="#f8fafc"/><circle cx="50" cy="20" r="12" fill="#e5e7eb"/><rect x="35" y="34" width="30" height="70" rx="14" fill="#e5e7eb"/><path d="M35 45 L18 92" stroke="#e5e7eb" stroke-width="9" stroke-linecap="round"/><path d="M65 45 L82 92" stroke="#e5e7eb" stroke-width="9" stroke-linecap="round"/><path d="M43 103 L36 158" stroke="#e5e7eb" stroke-width="10" stroke-linecap="round"/><path d="M57 103 L64 158" stroke="#e5e7eb" stroke-width="10" stroke-linecap="round"/>' . $markers . '</svg>';
    }

    /**
     * Anatomical marker positions in the shared 100x180 SVG viewBox, matching the
     * coordinates used by the interactive body map on the frontend so the persisted
     * heat map and the reviewer editor never disagree on where a region sits.
     *
     * @return array{0: float, 1: float}
     */
    private function regionCoordinates(string $rawLabel, string $side, int $fallbackIndex): array
    {
        $key = strtolower(trim($rawLabel));
        $key = str_replace('&', 'and', $key);
        $key = (string) preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');

        $map = [
            'neck' => [50.0, 30.0],
            'shoulders' => [50.0, 40.0],
            'upper_back' => [50.0, 60.0],
            'lower_back' => [50.0, 85.0],
            'elbows' => [27.0, 68.0],
            'wrists_hands' => [18.0, 92.0],
            'hips' => [50.0, 100.0],
            'knees' => [40.0, 130.0],
            'ankles_feet' => [36.0, 158.0],
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Unmapped region name: stack within the relevant body zone rather than
        // overlapping the known markers above.
        $zoneTop = $side === 'back' ? 40.0 : 30.0;

        return [50.0, $zoneTop + ($fallbackIndex * 14.0)];
    }
}
