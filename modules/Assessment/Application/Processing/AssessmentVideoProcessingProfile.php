<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application\Processing;

final class AssessmentVideoProcessingProfile
{
    /**
     * @param list<string> $outputTypes
     */
    public function __construct(
        public readonly string $tier,
        public readonly string $mediaPipeModel,
        public readonly ?string $heavyModelStrategy,
        public readonly int $maxDurationSeconds,
        public readonly float $sampledFps,
        public readonly int $maxResolutionWidth,
        public readonly int $maxResolutionHeight,
        public readonly string $queueName,
        public readonly string $queuePriority,
        public readonly string $reportDepth,
        public readonly array $outputTypes,
        public readonly string $retentionRule,
        public readonly bool $requiresAccessAudit,
        public readonly int $workerConcurrency,
    ) {}

    /** @return array<string, mixed> */
    public function toWorkerPayload(): array
    {
        return [
            'tier' => $this->tier,
            'mediapipe_model' => $this->mediaPipeModel,
            'heavy_model_strategy' => $this->heavyModelStrategy,
            'max_duration_seconds' => $this->maxDurationSeconds,
            'sampled_fps' => $this->sampledFps,
            'max_resolution' => [
                'width' => $this->maxResolutionWidth,
                'height' => $this->maxResolutionHeight,
            ],
            'queue_priority' => $this->queuePriority,
            'report_depth' => $this->reportDepth,
            'output_types' => $this->outputTypes,
            'retention_rule' => $this->retentionRule,
            'requires_access_audit' => $this->requiresAccessAudit,
            'worker_concurrency' => $this->workerConcurrency,
        ];
    }
}
