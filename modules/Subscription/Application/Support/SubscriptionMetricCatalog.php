<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\Support;

use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;

final class SubscriptionMetricCatalog
{
    public const MAX_WORKSITES = 'max_worksites';
    public const MAX_USERS = 'max_users';
    public const MAX_ASSESSMENTS_PER_MONTH = 'max_assessments_per_month';
    public const VIDEO_STORAGE_GB = 'video_storage_gb';
    public const VIDEO_STORAGE_USED_MB = 'video_storage_used_mb';
    public const AI_SCORING_CREDITS_PER_MONTH = 'ai_scoring_credits_per_month';
    public const AI_SCORING_CREDITS_USED = 'ai_scoring_credits_used';

    public function usageMetric(string $metric): string
    {
        return match ($metric) {
            'worksites' => self::MAX_WORKSITES,
            'users' => self::MAX_USERS,
            'assessments' => self::MAX_ASSESSMENTS_PER_MONTH,
            self::VIDEO_STORAGE_GB => self::VIDEO_STORAGE_USED_MB,
            self::AI_SCORING_CREDITS_PER_MONTH => self::AI_SCORING_CREDITS_USED,
            default => trim($metric),
        };
    }

    public function limitMetric(string $metric): string
    {
        return match ($metric) {
            'worksites', self::MAX_WORKSITES => self::MAX_WORKSITES,
            'users', self::MAX_USERS => self::MAX_USERS,
            'assessments', self::MAX_ASSESSMENTS_PER_MONTH => self::MAX_ASSESSMENTS_PER_MONTH,
            self::VIDEO_STORAGE_USED_MB, self::VIDEO_STORAGE_GB => self::VIDEO_STORAGE_GB,
            self::AI_SCORING_CREDITS_USED, self::AI_SCORING_CREDITS_PER_MONTH => self::AI_SCORING_CREDITS_PER_MONTH,
            default => trim($metric),
        };
    }

    public function resolveLimit(SubscriptionPlan $plan, string $metric): ?int
    {
        $limitMetric = $this->limitMetric($metric);
        $value = $plan->getFeature($limitMetric);

        if (!is_numeric($value)) {
            return null;
        }

        return match ($limitMetric) {
            self::VIDEO_STORAGE_GB => max(0, (int) ceil(((float) $value) * 1024)),
            default => max(0, (int) $value),
        };
    }
}
