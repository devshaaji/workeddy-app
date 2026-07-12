<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Contracts;

use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;

interface ISubscriptionRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function createSubscription(array $data): Subscription;

    public function findSubscriptionByUuid(string $uuid): ?Subscription;

    public function findByOrganizationId(int $organizationId): ?Subscription;

    public function findActiveByOrganizationId(int $organizationId): ?Subscription;

    /**
     * @param array<string, mixed> $data
     */
    public function updateSubscription(Subscription $subscription, array $data): Subscription;

    public function cancelSubscription(string $uuid, \DateTimeImmutable $cancelledAt, ?string $reason): Subscription;

    public function changePlan(string $uuid, string $newPlanCode, string $newPlanName, \DateTimeImmutable $effectiveDate): Subscription;

    /**
     * @param array<string, mixed> $filters
     * @return list<Subscription>
     */
    public function listSubscriptions(array $filters = []): array;

    /**
     * Active, auto-renewing subscriptions whose current period has ended
     * as of `$asOf`. Used by the renewal sweep (subscription-renewal-sweep
     * cron / bin/console subscription:renewal:sweep).
     *
     * @return list<Subscription>
     */
    public function findDueForRenewal(\DateTimeImmutable $asOf): array;
}
