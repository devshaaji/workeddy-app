<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Entities;

/**
 * Tracks current-period consumption against plan limits for one
 * subscription (e.g. worksites, users, assessments, video_storage_used_mb,
 * ai_scoring_credits_used). Enables limit enforcement, "X of Y used" UI,
 * and upgrade nudges.
 */
final class SubscriptionUsage
{
    /**
     * @param array<string, int> $usageData
     */
    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly \DateTimeImmutable $periodStart,
        public readonly \DateTimeImmutable $periodEnd,
        public readonly array $usageData,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    public function getUsage(string $key, int $default = 0): int
    {
        return (int) ($this->usageData[$key] ?? $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subscription_uuid' => $this->subscriptionUuid,
            'period_start' => $this->periodStart->format('Y-m-d'),
            'period_end' => $this->periodEnd->format('Y-m-d'),
            'usage' => $this->usageData,
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }
}
