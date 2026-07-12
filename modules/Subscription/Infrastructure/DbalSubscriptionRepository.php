<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Infrastructure;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\DateFormatter;
use Doctrine\DBAL\Connection;

final class DbalSubscriptionRepository implements ISubscriptionRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?IClock $clock = null,
    ) {}

    public function createSubscription(array $data): Subscription
    {
        $now = $data['updated_at'] ?? $this->now();
        $this->connection->insert('subscriptions', [
            'uuid' => (string) $data['uuid'],
            'organization_id' => (int) $data['organization_id'],
            'organization_uuid' => (string) $data['organization_uuid'],
            'plan_code' => (string) $data['plan_code'],
            'plan_name' => (string) $data['plan_name'],
            'status' => $this->normalizeStatus($data['status'] ?? SubscriptionStatus::PENDING_ACTIVATION),
            'billing_cycle' => (string) ($data['billing_cycle'] ?? 'monthly'),
            'start_date' => $this->dateTime($data['start_date'] ?? $now)->format('Y-m-d H:i:s'),
            'expiry_date' => $this->nullableDateTime($data['expiry_date'] ?? null)?->format('Y-m-d H:i:s'),
            'activated_at' => $this->nullableDateTime($data['activated_at'] ?? null)?->format('Y-m-d H:i:s'),
            'suspended_at' => $this->nullableDateTime($data['suspended_at'] ?? null)?->format('Y-m-d H:i:s'),
            'suspended_reason' => $data['suspended_reason'] ?? null,
            'cancelled_at' => $this->nullableDateTime($data['cancelled_at'] ?? null)?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $data['cancellation_reason'] ?? null,
            'auto_renew' => (bool) ($data['auto_renew'] ?? true),
            'current_period_start' => $this->nullableDateTime($data['current_period_start'] ?? null)?->format('Y-m-d H:i:s'),
            'current_period_end' => $this->nullableDateTime($data['current_period_end'] ?? null)?->format('Y-m-d H:i:s'),
            'created_at' => $this->dateTime($data['created_at'] ?? $now)->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return $this->findSubscriptionByUuid((string) $data['uuid']);
    }

    public function findSubscriptionByUuid(string $uuid): ?Subscription
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM subscriptions WHERE uuid = ?', [$uuid]);

        return $row === false ? null : $this->map($row);
    }

    public function findByOrganizationId(int $organizationId): ?Subscription
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM subscriptions WHERE organization_id = ? ORDER BY id DESC LIMIT 1',
            [$organizationId],
        );

        return $row === false ? null : $this->map($row);
    }

    public function findActiveByOrganizationId(int $organizationId): ?Subscription
    {
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM subscriptions WHERE organization_id = ? AND status IN ('active', 'pending_activation') ORDER BY id DESC LIMIT 1",
            [$organizationId],
        );

        return $row === false ? null : $this->map($row);
    }

    public function updateSubscription(Subscription $subscription, array $data): Subscription
    {
        $payload = [];
        foreach (['plan_code', 'plan_name', 'billing_cycle', 'suspended_reason', 'cancellation_reason'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        foreach (['start_date', 'expiry_date', 'activated_at', 'suspended_at', 'cancelled_at', 'current_period_start', 'current_period_end'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $this->nullableDateTime($data[$field])?->format('Y-m-d H:i:s');
            }
        }
        if (array_key_exists('status', $data)) {
            $payload['status'] = $this->normalizeStatus($data['status']);
        }
        if (array_key_exists('auto_renew', $data)) {
            $payload['auto_renew'] = (bool) $data['auto_renew'];
        }
        $payload['updated_at'] = $this->dateTime($data['updated_at'] ?? $this->now())->format('Y-m-d H:i:s');

        $this->connection->update('subscriptions', $payload, ['uuid' => $subscription->uuid]);

        return $this->findSubscriptionByUuid($subscription->uuid);
    }

    public function cancelSubscription(string $uuid, \DateTimeImmutable $cancelledAt, ?string $reason): Subscription
    {
        $subscription = $this->findSubscriptionByUuid($uuid);
        if ($subscription === null) {
            throw new NotFoundException('Subscription not found.');
        }

        return $this->updateSubscription($subscription, [
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => $cancelledAt,
            'cancellation_reason' => $reason,
            'auto_renew' => false,
            'updated_at' => $cancelledAt,
        ]);
    }

    public function changePlan(string $uuid, string $newPlanCode, string $newPlanName, \DateTimeImmutable $effectiveDate): Subscription
    {
        $subscription = $this->findSubscriptionByUuid($uuid);
        if ($subscription === null) {
            throw new NotFoundException('Subscription not found.');
        }

        return $this->updateSubscription($subscription, [
            'plan_code' => $newPlanCode,
            'plan_name' => $newPlanName,
            'updated_at' => $effectiveDate,
        ]);
    }

    public function listSubscriptions(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('subscriptions')
            ->orderBy('id', 'DESC');

        if (isset($filters['organization_id'])) {
            $qb->andWhere('organization_id = :organization_id')->setParameter('organization_id', (int) $filters['organization_id']);
        }
        if (isset($filters['status'])) {
            $qb->andWhere('status = :status')->setParameter('status', $this->normalizeStatus($filters['status']));
        }

        return array_map(
            fn(array $row): Subscription => $this->map($row),
            $qb->fetchAllAssociative(),
        );
    }

    public function findDueForRenewal(\DateTimeImmutable $asOf): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM subscriptions
             WHERE status = 'active'
               AND auto_renew = 1
               AND COALESCE(current_period_end, expiry_date) IS NOT NULL
               AND COALESCE(current_period_end, expiry_date) <= ?
             ORDER BY COALESCE(current_period_end, expiry_date) ASC",
            [$asOf->format('Y-m-d H:i:s')],
        );

        return array_map(fn(array $row): Subscription => $this->map($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): Subscription
    {
        return new Subscription(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            organizationId: (int) $row['organization_id'],
            organizationUuid: (string) $row['organization_uuid'],
            planCode: (string) $row['plan_code'],
            planName: (string) $row['plan_name'],
            status: SubscriptionStatus::from((string) $row['status']),
            billingCycle: (string) $row['billing_cycle'],
            startDate: DateFormatter::fromNaiveDbString($row['start_date']) ?? new \DateTimeImmutable(),
            expiryDate: $row['expiry_date'] !== null ? DateFormatter::fromNaiveDbString($row['expiry_date']) : null,
            activatedAt: $row['activated_at'] !== null ? DateFormatter::fromNaiveDbString($row['activated_at']) : null,
            suspendedAt: $row['suspended_at'] !== null ? DateFormatter::fromNaiveDbString($row['suspended_at']) : null,
            suspendedReason: $row['suspended_reason'] !== null ? (string) $row['suspended_reason'] : null,
            cancelledAt: $row['cancelled_at'] !== null ? DateFormatter::fromNaiveDbString($row['cancelled_at']) : null,
            cancellationReason: $row['cancellation_reason'] !== null ? (string) $row['cancellation_reason'] : null,
            autoRenew: (bool) $row['auto_renew'],
            createdAt: DateFormatter::fromNaiveDbString($row['created_at']) ?? new \DateTimeImmutable(),
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at']) ?? new \DateTimeImmutable(),
            currentPeriodStart: isset($row['current_period_start']) && $row['current_period_start'] !== null ? DateFormatter::fromNaiveDbString($row['current_period_start']) : null,
            currentPeriodEnd: isset($row['current_period_end']) && $row['current_period_end'] !== null ? DateFormatter::fromNaiveDbString($row['current_period_end']) : null,
        );
    }

    private function normalizeStatus(mixed $status): string
    {
        return $status instanceof SubscriptionStatus ? $status->value : (string) $status;
    }

    private function dateTime(mixed $value): \DateTimeImmutable
    {
        return $value instanceof \DateTimeImmutable ? $value : new \DateTimeImmutable((string) $value);
    }

    private function nullableDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->dateTime($value);
    }

    private function now(): \DateTimeImmutable
    {
        return ($this->clock ?? new \WorkEddy\Platform\Clock\SystemClock())->now();
    }
}
