<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettings;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ValidationException;

/**
 * Admin-tier use case for defining a new SaaS plan tier (e.g. starter,
 * professional, enterprise) with its feature/limit map.
 */
final class CreateSubscriptionPlan
{
    public function __construct(
        private readonly ISubscriptionPlanRepository $repository,
        private readonly SubscriptionSettings $settings,
        private readonly IClock $clock,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): SubscriptionPlan
    {
        foreach (['code', 'name'] as $field) {
            if (empty($data[$field])) {
                throw new ValidationException([$field => ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
            }
        }

        $code = strtolower(trim((string) $data['code']));

        if ($this->repository->findByCode($code) !== null) {
            throw new ConflictException(sprintf('A plan with code "%s" already exists.', $code));
        }

        $now = $this->clock->now();

        return $this->repository->upsert([
            'code' => $code,
            'name' => (string) $data['name'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'billing_cycle' => (string) ($data['billing_cycle'] ?? $this->settings->defaultBillingCycle()),
            'price' => (float) ($data['price'] ?? 0.0),
            'currency' => (string) ($data['currency'] ?? $this->settings->defaultCurrency()),
            'features' => is_array($data['features'] ?? null) ? $data['features'] : [],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'display_order' => isset($data['display_order']) ? (int) $data['display_order'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
