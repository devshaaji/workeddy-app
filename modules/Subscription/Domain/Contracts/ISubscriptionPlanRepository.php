<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Contracts;

use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;

interface ISubscriptionPlanRepository
{
    public function findByCode(string $code): ?SubscriptionPlan;

    /**
     * @return list<SubscriptionPlan>
     */
    public function listActive(): array;

    /**
     * @return list<SubscriptionPlan>
     */
    public function listAll(): array;

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(array $data): SubscriptionPlan;
}
