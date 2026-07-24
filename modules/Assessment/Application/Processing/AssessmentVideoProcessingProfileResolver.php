<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application\Processing;

final class AssessmentVideoProcessingProfileResolver
{
    /**
     * @param array<string, mixed> $subscriptionFeatures
     */
    public function resolve(?string $planCode, array $subscriptionFeatures = []): AssessmentVideoProcessingProfile
    {
        $base = match (strtolower(trim((string) $planCode))) {
            'pro' => new AssessmentVideoProcessingProfile('pro', 'full', null, 600, 5.0, 1280, 720, 'assessment_video_jobs.high', 'high', 'timeline', ['timeline', 'thumbnail', 'pose_video', 'standard_report'], 'retain_policy', true, 3),
            'enterprise' => new AssessmentVideoProcessingProfile('enterprise', 'full', 'heavy_on_risky_frames', 1800, 10.0, 1920, 1080, 'assessment_video_jobs.highest', 'highest', 'advanced', ['timeline', 'thumbnail', 'pose_video', 'blurred_video', 'advanced_report', 'segments'], 'policy_plus_evidence', true, 6),
            'basic' => new AssessmentVideoProcessingProfile('basic', 'lite', 'limited_full_on_low_confidence', 180, 3.0, 960, 540, 'assessment_video_jobs.normal', 'normal', 'standard', ['thumbnail', 'pose_video', 'standard_report'], 'retain_policy', true, 2),
            default => new AssessmentVideoProcessingProfile('trial', 'lite', null, 15, 1.0, 640, 360, 'assessment_video_jobs.low', 'low', 'basic', ['thumbnail', 'pose_video', 'blurred_video', 'basic_flags'], 'delete_after_processing', true, 1),
        };
        $overrides = is_array($subscriptionFeatures['video_processing_profile'] ?? null)
            ? $subscriptionFeatures['video_processing_profile']
            : [];
        if ($overrides === []) {
            return $base;
        }

        $queuePriority = (string) ($overrides['queue_priority'] ?? $base->queuePriority);

        return new AssessmentVideoProcessingProfile(
            tier: $base->tier,
            mediaPipeModel: (string) ($overrides['mediapipe_model'] ?? $base->mediaPipeModel),
            heavyModelStrategy: array_key_exists('heavy_model_strategy', $overrides) ? ($overrides['heavy_model_strategy'] !== null ? (string) $overrides['heavy_model_strategy'] : null) : $base->heavyModelStrategy,
            maxDurationSeconds: (int) ($overrides['max_duration_seconds'] ?? $base->maxDurationSeconds),
            sampledFps: (float) ($overrides['sampled_fps'] ?? $base->sampledFps),
            maxResolutionWidth: (int) ($overrides['max_resolution_width'] ?? $base->maxResolutionWidth),
            maxResolutionHeight: (int) ($overrides['max_resolution_height'] ?? $base->maxResolutionHeight),
            queueName: 'assessment_video_jobs.' . $queuePriority,
            queuePriority: $queuePriority,
            reportDepth: (string) ($overrides['report_depth'] ?? $base->reportDepth),
            outputTypes: is_array($overrides['output_types'] ?? null) ? array_values(array_map('strval', $overrides['output_types'])) : $base->outputTypes,
            retentionRule: (string) ($overrides['retention_rule'] ?? $base->retentionRule),
            requiresAccessAudit: (bool) ($overrides['requires_access_audit'] ?? $base->requiresAccessAudit),
            workerConcurrency: (int) ($overrides['worker_concurrency'] ?? $base->workerConcurrency),
        );
    }
}
