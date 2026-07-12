<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Entities;

/**
 * SaaS tier definition. Feature limits and flags (max_worksites, max_users,
 * max_assessments_per_month, video_storage_gb, ai_scoring_credits_per_month,
 * has_export_access, etc.) live in the dynamic `features` map so new tiers
 * or entitlements can be introduced without a schema change.
 */
final class SubscriptionPlan
{
    /**
     * @param array<string, mixed> $features
     */
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $billingCycle,
        public readonly float $price,
        public readonly string $currency,
        public readonly array $features,
        public readonly bool $isActive,
        public readonly ?int $displayOrder,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Retrieve a feature value safely from the dynamic features dictionary.
     */
    public function getFeature(string $key, mixed $default = null): mixed
    {
        return $this->features[$key] ?? $default;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) $this->getFeature($key, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'billing_cycle' => $this->billingCycle,
            'price' => $this->price,
            'currency' => $this->currency,
            'features' => $this->features,
            'is_active' => $this->isActive,
            'display_order' => $this->displayOrder,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];

        if ($includeInternalId) {
            $data['id'] = $this->id;
        }

        return $data;
    }
}
