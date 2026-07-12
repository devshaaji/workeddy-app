<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Subscription;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Modules\Subscription\Application\UseCases\CheckSubscriptionLimits;
use WorkEddy\Modules\Subscription\Application\UseCases\RecordUsage;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRepository;
use WorkEddy\Modules\Subscription\Domain\Entities\Subscription;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionUsage;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Modules\Subscription\Domain\Events\SubscriptionLimitExceeded;
use WorkEddy\Platform\Clock\FrozenClock;
use WorkEddy\Platform\Events\EventPublisherInterface;

final class SubscriptionQuotaContractsTest extends TestCase
{
    public function test_check_subscription_limits_maps_plan_limit_to_usage_metric(): void
    {
        $metrics = new SubscriptionMetricCatalog();
        $useCase = new CheckSubscriptionLimits(
            new TestSubscriptionRepository(),
            new TestSubscriptionPlanRepository([
                'video_storage_gb' => 2,
                'max_worksites' => 5,
            ]),
            new TestSubscriptionUsageRepository([
                'video_storage_used_mb' => 512,
                'max_worksites' => 3,
            ]),
            new FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')),
            $metrics,
        );

        $videoLimits = $useCase->forOrganization(3, SubscriptionMetricCatalog::VIDEO_STORAGE_GB);
        self::assertSame(2048, $videoLimits->limit);
        self::assertSame(512, $videoLimits->used);
        self::assertFalse($videoLimits->wouldExceed(1536));
        self::assertTrue($videoLimits->wouldExceed(1537));

        $worksiteLimits = $useCase->forOrganization(3, SubscriptionMetricCatalog::MAX_WORKSITES);
        self::assertSame(5, $worksiteLimits->limit);
        self::assertSame(3, $worksiteLimits->used);
    }

    public function test_record_usage_publishes_limit_exceeded_for_normalized_metric(): void
    {
        $events = new RecordingEventPublisher();
        $usageRepository = new TestSubscriptionUsageRepository(['video_storage_used_mb' => 1020]);
        $useCase = new RecordUsage(
            new TestSubscriptionRepository(),
            new TestSubscriptionPlanRepository(['video_storage_gb' => 1]),
            $usageRepository,
            new FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')),
            $events,
            new SubscriptionMetricCatalog(),
        );

        $usage = $useCase->forOrganization(3, SubscriptionMetricCatalog::VIDEO_STORAGE_USED_MB, 10);

        self::assertSame(1030, $usage->getUsage('video_storage_used_mb'));
        self::assertSame(SubscriptionLimitExceeded::NAME, $events->published[0]['eventName']);
        self::assertSame('video_storage_used_mb', $events->published[0]['payload']['metric']);
        self::assertSame(1024, $events->published[0]['payload']['limit']);
        self::assertSame(1030, $events->published[0]['payload']['used']);
    }
}

final class TestSubscriptionRepository implements ISubscriptionRepository
{
    public function createSubscription(array $data): Subscription { throw new \RuntimeException('Not used.'); }
    public function findSubscriptionByUuid(string $uuid): ?Subscription { return null; }
    public function findByOrganizationId(int $organizationId): ?Subscription { return $this->findActiveByOrganizationId($organizationId); }
    public function findActiveByOrganizationId(int $organizationId): ?Subscription
    {
        return new Subscription(
            id: 1,
            uuid: 'sub-uuid',
            organizationId: $organizationId,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            planCode: 'pro',
            planName: 'Pro',
            status: SubscriptionStatus::ACTIVE,
            billingCycle: 'monthly',
            startDate: new \DateTimeImmutable('2026-07-01'),
            expiryDate: null,
            activatedAt: new \DateTimeImmutable('2026-07-01'),
            suspendedAt: null,
            suspendedReason: null,
            cancelledAt: null,
            cancellationReason: null,
            autoRenew: true,
            createdAt: new \DateTimeImmutable('2026-07-01'),
            updatedAt: new \DateTimeImmutable('2026-07-01'),
        );
    }
    public function updateSubscription(Subscription $subscription, array $data): Subscription { return $subscription; }
    public function cancelSubscription(string $uuid, \DateTimeImmutable $cancelledAt, ?string $reason): Subscription { throw new \RuntimeException('Not used.'); }
    public function changePlan(string $uuid, string $newPlanCode, string $newPlanName, \DateTimeImmutable $effectiveDate): Subscription { throw new \RuntimeException('Not used.'); }
    public function listSubscriptions(array $filters = []): array { return []; }
    public function findDueForRenewal(\DateTimeImmutable $asOf): array { return []; }
}

final class TestSubscriptionPlanRepository implements ISubscriptionPlanRepository
{
    /** @param array<string, mixed> $features */
    public function __construct(private array $features) {}

    public function findByCode(string $code): ?SubscriptionPlan
    {
        return new SubscriptionPlan(
            id: 1,
            code: $code,
            name: 'Pro',
            description: null,
            billingCycle: 'monthly',
            price: 0.0,
            currency: 'USD',
            features: $this->features,
            isActive: true,
            displayOrder: 1,
            createdAt: new \DateTimeImmutable('2026-07-01'),
            updatedAt: new \DateTimeImmutable('2026-07-01'),
        );
    }
    public function listActive(): array { return []; }
    public function listAll(): array { return []; }
    public function upsert(array $data): SubscriptionPlan { throw new \RuntimeException('Not used.'); }
}

final class TestSubscriptionUsageRepository implements ISubscriptionUsageRepository
{
    /** @param array<string, int> $usageData */
    public function __construct(private array $usageData) {}

    public function recordUsage(string $subscriptionUuid, string $metric, int $increment, \DateTimeImmutable $now): SubscriptionUsage
    {
        $this->usageData[$metric] = max(0, ($this->usageData[$metric] ?? 0) + $increment);

        return $this->current($subscriptionUuid);
    }

    public function getCurrentPeriodUsage(string $subscriptionUuid, \DateTimeImmutable $now): SubscriptionUsage
    {
        return $this->current($subscriptionUuid);
    }

    public function resetPeriod(string $subscriptionUuid, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): SubscriptionUsage
    {
        $this->usageData = [];

        return $this->current($subscriptionUuid, $periodStart, $periodEnd);
    }

    private function current(
        string $subscriptionUuid,
        ?\DateTimeImmutable $periodStart = null,
        ?\DateTimeImmutable $periodEnd = null,
    ): SubscriptionUsage {
        return new SubscriptionUsage(
            subscriptionUuid: $subscriptionUuid,
            periodStart: $periodStart ?? new \DateTimeImmutable('2026-07-01'),
            periodEnd: $periodEnd ?? new \DateTimeImmutable('2026-07-31'),
            usageData: $this->usageData,
            updatedAt: new \DateTimeImmutable('2026-07-08 10:00:00'),
        );
    }
}

final class RecordingEventPublisher implements EventPublisherInterface
{
    public array $published = [];

    public function publish(string $eventName, array $payload, string $idempotencyKey): void
    {
        $this->published[] = compact('eventName', 'payload', 'idempotencyKey');
    }
}
