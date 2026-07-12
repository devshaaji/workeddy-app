<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\Listeners;

use WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Services\SubscriptionProrationCalculator;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Events\IAsyncEventListener;
use WorkEddy\Platform\Logging\ILoggerFactory;

/**
 * Reacts to `subscription.plan_changed` by generating a prorated invoice
 * for the remainder of the current billing period \u2014 **upgrades only**.
 *
 * v1 scope decision (see docs/subscription-rework.md): downgrades are
 * computed (for logging/audit) but never invoiced negatively, and there is
 * no credit ledger yet. A downgrade's unused-time credit is simply logged
 * and discarded in this slice; a credit ledger and/or deferring the
 * downgrade to take effect at the next billing period are explicit Phase 2
 * scope, not built here.
 *
 * The subscription's `plan_code` has already been updated by the time this
 * listener runs, so old/new plan prices are looked up independently by
 * the event's `old_plan_code`/`new_plan_code` rather than read off the
 * subscription. `current_period_start`/`current_period_end` are unaffected
 * by a plan change, so those are read directly off the (now-updated)
 * subscription record.
 */
final class GenerateProrationInvoiceOnPlanChange implements IAsyncEventListener
{
    public function __construct(
        private readonly GenerateInvoice $generateInvoice,
        private readonly ISubscriptionRepository $subscriptions,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly IClock $clock,
        private readonly ILoggerFactory $loggerFactory,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload): void
    {
        $logger = $this->loggerFactory->channel('Subscription');

        $subscriptionUuid = isset($payload['subscription_uuid']) ? (string) $payload['subscription_uuid'] : '';
        $organizationId = isset($payload['organization_id']) ? (int) $payload['organization_id'] : 0;
        $oldPlanCode = isset($payload['old_plan_code']) ? (string) $payload['old_plan_code'] : '';
        $newPlanCode = isset($payload['new_plan_code']) ? (string) $payload['new_plan_code'] : '';

        if ($subscriptionUuid === '' || $organizationId <= 0 || $oldPlanCode === '' || $newPlanCode === '') {
            return;
        }

        $subscription = $this->subscriptions->findSubscriptionByUuid($subscriptionUuid);
        if ($subscription === null || $subscription->currentPeriodStart === null || $subscription->currentPeriodEnd === null) {
            // No period boundaries to prorate against (e.g. a subscription
            // predating the current_period_start/end rollout). Skip rather
            // than guess.
            $logger->warning('Skipped proration: subscription has no current billing period recorded.', [
                'subscription_uuid' => $subscriptionUuid,
            ]);

            return;
        }

        $oldPlan = $this->plans->findByCode($oldPlanCode);
        $newPlan = $this->plans->findByCode($newPlanCode);
        if ($oldPlan === null || $newPlan === null) {
            $logger->warning('Skipped proration: old or new plan not found.', [
                'subscription_uuid' => $subscriptionUuid,
                'old_plan_code' => $oldPlanCode,
                'new_plan_code' => $newPlanCode,
            ]);

            return;
        }

        $effectiveDate = isset($payload['effective_date']) && $payload['effective_date'] !== ''
            ? new \DateTimeImmutable((string) $payload['effective_date'])
            : $this->clock->now();

        $proration = SubscriptionProrationCalculator::calculate(
            oldPlanPrice: $oldPlan->price,
            newPlanPrice: $newPlan->price,
            periodStart: $subscription->currentPeriodStart,
            periodEnd: $subscription->currentPeriodEnd,
            effectiveDate: $effectiveDate,
        );

        if (!$proration->isUpgrade()) {
            // Downgrade (or a wash): explicit Phase 2 scope. Log clearly
            // rather than silently discarding the credit.
            $logger->info('Downgrade proration credit not invoiced (Phase 2 scope: no credit ledger / scheduled downgrade in v1).', [
                'subscription_uuid' => $subscriptionUuid,
                'old_plan_code' => $oldPlanCode,
                'new_plan_code' => $newPlanCode,
            ] + $proration->toArray());

            return;
        }

        try {
            $this->generateInvoice->execute(
                organizationId: $organizationId,
                quotationUuid: null,
                items: [[
                    'description' => sprintf(
                        'Prorated upgrade: %s \u2192 %s (%d of %d days remaining in current period)',
                        $oldPlan->name,
                        $newPlan->name,
                        $proration->daysRemaining,
                        $proration->daysInPeriod,
                    ),
                    'quantity' => 1,
                    'unit_price' => $proration->netAmount,
                ]],
                currency: $newPlan->currency,
                daysUntilDue: 7,
                actorId: null,
                subscriptionUuid: $subscriptionUuid,
            );
        } catch (\Throwable $exception) {
            $logger->error('Failed to generate proration invoice on plan upgrade.', [
                'subscription_uuid' => $subscriptionUuid,
                'old_plan_code' => $oldPlanCode,
                'new_plan_code' => $newPlanCode,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
