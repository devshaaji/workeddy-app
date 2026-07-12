<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Infrastructure;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\DateFormatter;
use Doctrine\DBAL\Connection;

final class DbalSubscriptionPlanRepository implements ISubscriptionPlanRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?IClock $clock = null,
    ) {}

    public function findByCode(string $code): ?SubscriptionPlan
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM subscription_plans WHERE code = ?', [$code]);

        return $row === false ? null : $this->map($row);
    }

    public function listActive(): array
    {
        return array_map(
            fn(array $row): SubscriptionPlan => $this->map($row),
            $this->connection->fetchAllAssociative(
                'SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY display_order ASC, price ASC',
            ),
        );
    }

    public function listAll(): array
    {
        return array_map(
            fn(array $row): SubscriptionPlan => $this->map($row),
            $this->connection->fetchAllAssociative('SELECT * FROM subscription_plans ORDER BY display_order ASC, name ASC'),
        );
    }

    public function upsert(array $data): SubscriptionPlan
    {
        $code = (string) $data['code'];
        $existing = $this->findByCode($code);
        $now = $data['updated_at'] ?? $this->now();

        $payload = [
            'name' => (string) $data['name'],
            'description' => array_key_exists('description', $data) ? $data['description'] : $existing?->description,
            'billing_cycle' => (string) ($data['billing_cycle'] ?? $existing?->billingCycle ?? 'monthly'),
            'price' => (float) ($data['price'] ?? $existing?->price ?? 0.0),
            'currency' => (string) ($data['currency'] ?? $existing?->currency ?? 'USD'),
            'features' => json_encode($data['features'] ?? $existing?->features ?? [], JSON_THROW_ON_ERROR),
            'is_active' => (bool) ($data['is_active'] ?? $existing?->isActive ?? true),
            'display_order' => array_key_exists('display_order', $data) ? $data['display_order'] : $existing?->displayOrder,
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ];

        if ($existing === null) {
            $this->connection->insert('subscription_plans', $payload + [
                'code' => $code,
                'created_at' => ($data['created_at'] ?? $now)->format('Y-m-d H:i:s'),
            ]);
        } else {
            $this->connection->update('subscription_plans', $payload, ['code' => $code]);
        }

        return $this->findByCode($code);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): SubscriptionPlan
    {
        $features = is_string($row['features']) ? json_decode($row['features'], true) : $row['features'];

        return new SubscriptionPlan(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            billingCycle: (string) $row['billing_cycle'],
            price: (float) $row['price'],
            currency: (string) $row['currency'],
            features: is_array($features) ? $features : [],
            isActive: (bool) $row['is_active'],
            displayOrder: $row['display_order'] !== null ? (int) $row['display_order'] : null,
            createdAt: DateFormatter::fromNaiveDbString($row['created_at']) ?? new \DateTimeImmutable(),
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at']) ?? new \DateTimeImmutable(),
        );
    }

    private function now(): \DateTimeImmutable
    {
        return ($this->clock ?? new \WorkEddy\Platform\Clock\SystemClock())->now();
    }
}
