<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\ValueObjects;

use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;

/**
 * Immutable snapshot comparing a plan's feature limits against a
 * subscription's current usage for a single metric. Built by
 * CheckSubscriptionLimits so callers get a single, easy-to-render
 * comparison instead of re-deriving math from two separate entities.
 */
final class SubscriptionLimits
{
    private function __construct(
        public readonly string $metric,
        public readonly ?int $limit,
        public readonly int $used,
    ) {}

    public static function fromValues(string $metric, ?int $limit, int $used): self
    {
        return new self($metric, $limit, $used);
    }

    public static function forMetric(SubscriptionPlan $plan, ?SubscriptionUsage $usage, string $metric): self
    {
        $limit = $plan->getFeature($metric);
        $limit = is_numeric($limit) ? (int) $limit : null;

        return new self(
            metric: $metric,
            limit: $limit,
            used: $usage?->getUsage($metric) ?? 0,
        );
    }

    /**
     * A null limit means "unlimited" for this metric.
     */
    public function isUnlimited(): bool
    {
        return $this->limit === null;
    }

    public function remaining(): ?int
    {
        return $this->isUnlimited() ? null : max(0, $this->limit - $this->used);
    }

    public function isExceeded(): bool
    {
        return !$this->isUnlimited() && $this->used >= $this->limit;
    }

    public function wouldExceed(int $increment = 1): bool
    {
        return !$this->isUnlimited() && ($this->used + $increment) > $this->limit;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'limit' => $this->limit,
            'used' => $this->used,
            'remaining' => $this->remaining(),
            'unlimited' => $this->isUnlimited(),
            'exceeded' => $this->isExceeded(),
        ];
    }
}
