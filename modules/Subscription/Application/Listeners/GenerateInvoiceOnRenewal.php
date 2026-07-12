<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\Listeners;

use WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettings;
use WorkEddy\Platform\Events\IAsyncEventListener;
use WorkEddy\Platform\Logging\ILoggerFactory;

/**
 * Reacts to `subscription.renewed` by generating the invoice for the
 * upcoming billing period (advance billing \u2014 the sweep already advanced
 * `current_period_start`/`current_period_end` before publishing this
 * event). Due within `SubscriptionSettings::gracePeriodDays()`; if it goes
 * unpaid past that, SuspendOverdueSubscriptions will suspend the
 * subscription. Mirrors GenerateInvoiceOnActivation; failures are logged
 * and swallowed so a Billing-side issue never breaks the renewal sweep.
 */
final class GenerateInvoiceOnRenewal implements IAsyncEventListener
{
    public function __construct(
        private readonly GenerateInvoice $generateInvoice,
        private readonly ISubscriptionPlanRepository $plans,
        private readonly SubscriptionSettings $settings,
        private readonly ILoggerFactory $loggerFactory,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload): void
    {
        $logger = $this->loggerFactory->channel('Subscription');

        $organizationId = isset($payload['organization_id']) ? (int) $payload['organization_id'] : 0;
        $planCode = isset($payload['plan_code']) ? (string) $payload['plan_code'] : '';
        $subscriptionUuid = isset($payload['subscription_uuid']) ? (string) $payload['subscription_uuid'] : null;

        if ($organizationId <= 0 || $planCode === '') {
            return;
        }

        $plan = $this->plans->findByCode($planCode);
        if ($plan === null) {
            $logger->warning('Skipped renewal invoice generation for unknown plan code.', [
                'plan_code' => $planCode,
                'subscription_uuid' => $subscriptionUuid,
            ]);

            return;
        }

        if ($plan->price <= 0.0) {
            return;
        }

        $periodStart = isset($payload['period_start']) ? (string) $payload['period_start'] : null;
        $periodEnd = isset($payload['period_end']) ? (string) $payload['period_end'] : null;
        $description = $periodStart !== null && $periodEnd !== null
            ? sprintf('%s subscription (%s \u2013 %s)', $plan->name, substr($periodStart, 0, 10), substr($periodEnd, 0, 10))
            : sprintf('%s subscription renewal (%s)', $plan->name, $plan->billingCycle);

        try {
            $this->generateInvoice->execute(
                organizationId: $organizationId,
                quotationUuid: null,
                items: [[
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $plan->price,
                ]],
                currency: $plan->currency,
                daysUntilDue: max(1, $this->settings->gracePeriodDays()),
                actorId: null,
                subscriptionUuid: $subscriptionUuid,
            );
        } catch (\Throwable $exception) {
            $logger->error('Failed to generate invoice on subscription renewal.', [
                'organization_id' => $organizationId,
                'plan_code' => $planCode,
                'subscription_uuid' => $subscriptionUuid,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
