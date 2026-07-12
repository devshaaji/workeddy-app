<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Domain;

final class AiScoreOutput
{
    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $score
     * @param list<array<string, mixed>> $timeline
     * @param array<string, mixed> $flags
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $assessmentUuid,
        public readonly ?string $assessmentVideoUuid,
        public readonly string $scoreModel,
        public readonly string $scoreSource,
        public readonly string $modelVersion,
        public readonly ?float $confidence,
        public readonly array $metrics,
        public readonly array $score,
        public readonly array $timeline,
        public readonly array $flags,
        public readonly array $metadata,
        public readonly ?string $createdByWorker,
        public readonly ?string $createdAt,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'assessmentUuid' => $this->assessmentUuid,
            'assessmentVideoUuid' => $this->assessmentVideoUuid,
            'scoreModel' => $this->scoreModel,
            'scoreSource' => $this->scoreSource,
            'modelVersion' => $this->modelVersion,
            'confidence' => $this->confidence,
            'metrics' => $this->metrics,
            'score' => $this->score,
            'timeline' => $this->timeline,
            'flags' => $this->flags,
            'metadata' => $this->metadata,
            'createdByWorker' => $this->createdByWorker,
            'createdAt' => $this->createdAt,
        ];
    }
}
