<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Entities;

use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;

/**
 * A SaaS tier contract bound to a single Organization (1 active subscription
 * per organization). Replaces the legacy ISP customer_reference/bandwidth
 * model with a strict FK to the Organization module.
 *
 * `currentPeriodStart`/`currentPeriodEnd` track the active billing period
 * (advanced on each renewal) and are the source of truth for proration and
 * renewal math \u2014 `startDate` is the lifetime activation date and never
 * changes, `expiryDate` mirrors `currentPeriodEnd` for backward-compatible
 * display purposes.
 */
final class Subscription
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $planCode,
        public readonly string $planName,
        public readonly SubscriptionStatus $status,
        public readonly string $billingCycle,
        public readonly \DateTimeImmutable $startDate,
        public readonly ?\DateTimeImmutable $expiryDate,
        public readonly ?\DateTimeImmutable $activatedAt,
        public readonly ?\DateTimeImmutable $suspendedAt,
        public readonly ?string $suspendedReason,
        public readonly ?\DateTimeImmutable $cancelledAt,
        public readonly ?string $cancellationReason,
        public readonly bool $autoRenew,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?\DateTimeImmutable $currentPeriodStart = null,
        public readonly ?\DateTimeImmutable $currentPeriodEnd = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'uuid' => $this->uuid,
            'organization_id' => $this->organizationId,
            'organization_uuid' => $this->organizationUuid,
            'plan_code' => $this->planCode,
            'plan_name' => $this->planName,
            'status' => $this->status->value,
            'billing_cycle' => $this->billingCycle,
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'expiry_date' => $this->expiryDate?->format('Y-m-d H:i:s'),
            'current_period_start' => $this->currentPeriodStart?->format('Y-m-d H:i:s'),
            'current_period_end' => $this->currentPeriodEnd?->format('Y-m-d H:i:s'),
            'activated_at' => $this->activatedAt?->format('Y-m-d H:i:s'),
            'suspended_at' => $this->suspendedAt?->format('Y-m-d H:i:s'),
            'suspended_reason' => $this->suspendedReason,
            'cancelled_at' => $this->cancelledAt?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $this->cancellationReason,
            'auto_renew' => $this->autoRenew,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];

        if (!$includeInternalId) {
            return $data;
        }

        $data['id'] = $this->id;

        return $data;
    }
}
