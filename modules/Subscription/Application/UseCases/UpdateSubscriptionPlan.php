<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\NotFoundException;

/**
 * Admin-tier use case for editing an existing plan tier's price, features,
 * or active/visibility state. Does not retroactively change limits already
 * locked in for existing subscribers on this plan.
 */
final class UpdateSubscriptionPlan
{
    public function __construct(
        private readonly ISubscriptionPlanRepository $repository,
        private readonly IClock $clock,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(string $code, array $data): SubscriptionPlan
    {
        $existing = $this->repository->findByCode($code);
        if ($existing === null) {
            throw new NotFoundException('Subscription plan not found.');
        }

        $payload = ['code' => $code, 'updated_at' => $this->clock->now()];

        foreach (['name', 'description', 'billing_cycle', 'currency', 'display_order', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        if (array_key_exists('price', $data)) {
            $payload['price'] = (float) $data['price'];
        }
        if (array_key_exists('features', $data) && is_array($data['features'])) {
            $payload['features'] = $data['features'];
        }

        return $this->repository->upsert($payload);
    }
}
