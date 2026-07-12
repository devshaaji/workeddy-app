<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Infrastructure;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;
use WorkEddy\Shared\Support\DateFormatter;
use Doctrine\DBAL\Connection;

final class DbalSubscriptionUsageRepository implements ISubscriptionUsageRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function recordUsage(string $subscriptionUuid, string $metric, int $increment, \DateTimeImmutable $now): SubscriptionUsage
    {
        $current = $this->getCurrentPeriodUsage($subscriptionUuid, $now);
        $usageData = $current->usageData;
        $usageData[$metric] = max(0, ($usageData[$metric] ?? 0) + $increment);

        $this->connection->update('subscription_usage', [
            'usage_data' => json_encode($usageData, JSON_THROW_ON_ERROR),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ], [
            'subscription_uuid' => $subscriptionUuid,
            'period_start' => $current->periodStart->format('Y-m-d'),
        ]);

        return new SubscriptionUsage(
            subscriptionUuid: $subscriptionUuid,
            periodStart: $current->periodStart,
            periodEnd: $current->periodEnd,
            usageData: $usageData,
            updatedAt: $now,
        );
    }

    public function getCurrentPeriodUsage(string $subscriptionUuid, \DateTimeImmutable $now): SubscriptionUsage
    {
        [$periodStart, $periodEnd] = $this->currentMonthlyPeriod($now);

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM subscription_usage WHERE subscription_uuid = ? AND period_start = ?',
            [$subscriptionUuid, $periodStart->format('Y-m-d')],
        );

        if ($row !== false) {
            return $this->map($row);
        }

        $this->connection->insert('subscription_usage', [
            'subscription_uuid' => $subscriptionUuid,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'usage_data' => json_encode([], JSON_THROW_ON_ERROR),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return new SubscriptionUsage(
            subscriptionUuid: $subscriptionUuid,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            usageData: [],
            updatedAt: $now,
        );
    }

    public function resetPeriod(string $subscriptionUuid, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): SubscriptionUsage
    {
        $now = $periodStart;

        $existing = $this->connection->fetchAssociative(
            'SELECT * FROM subscription_usage WHERE subscription_uuid = ? AND period_start = ?',
            [$subscriptionUuid, $periodStart->format('Y-m-d')],
        );

        if ($existing !== false) {
            $this->connection->update('subscription_usage', [
                'period_end' => $periodEnd->format('Y-m-d'),
                'usage_data' => json_encode([], JSON_THROW_ON_ERROR),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ], [
                'subscription_uuid' => $subscriptionUuid,
                'period_start' => $periodStart->format('Y-m-d'),
            ]);
        } else {
            $this->connection->insert('subscription_usage', [
                'subscription_uuid' => $subscriptionUuid,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'usage_data' => json_encode([], JSON_THROW_ON_ERROR),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        return new SubscriptionUsage(
            subscriptionUuid: $subscriptionUuid,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            usageData: [],
            updatedAt: $now,
        );
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function currentMonthlyPeriod(\DateTimeImmutable $now): array
    {
        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $end = $now->modify('last day of this month')->setTime(23, 59, 59);

        return [$start, $end];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): SubscriptionUsage
    {
        $usageData = is_string($row['usage_data']) ? json_decode($row['usage_data'], true) : $row['usage_data'];

        return new SubscriptionUsage(
            subscriptionUuid: (string) $row['subscription_uuid'],
            periodStart: DateFormatter::fromNaiveDbString($row['period_start']) ?? new \DateTimeImmutable(),
            periodEnd: DateFormatter::fromNaiveDbString($row['period_end']) ?? new \DateTimeImmutable(),
            usageData: is_array($usageData) ? $usageData : [],
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at']) ?? new \DateTimeImmutable(),
        );
    }
}
