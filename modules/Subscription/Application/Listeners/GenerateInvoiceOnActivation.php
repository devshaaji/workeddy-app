<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\Listeners;

use WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Platform\Events\IAsyncEventListener;
use WorkEddy\Platform\Logging\ILoggerFactory;

/**
 * Reacts to `subscription.activated` by generating the first invoice for
 * the new subscription via the Billing module.
 *
 * Passes the organization's internal id as `organizationId` (Billing has
 * no Customer module concept — all invoices are Organization-billed; see
 * docs/modules-map.md §2.10) and `subscriptionUuid` so the resulting
 * invoice can be traced back to this subscription (see
 * billing_invoices.subscription_uuid). Failures here are logged and
 * swallowed so a Billing-side issue never breaks activation itself.
 */
final class GenerateInvoiceOnActivation implements IAsyncEventListener
{
    public function __construct(
        private readonly GenerateInvoice $generateInvoice,
        private readonly ISubscriptionPlanRepository $plans,
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

        if ($organizationId <= 0 || $planCode === '') {
            return;
        }

        $plan = $this->plans->findByCode($planCode);
        if ($plan === null) {
            $logger->warning('Skipped invoice generation for unknown plan code.', [
                'plan_code' => $planCode,
                'subscription_uuid' => $payload['subscription_uuid'] ?? null,
            ]);

            return;
        }

        if ($plan->price <= 0.0) {
            return;
        }

        try {
            $this->generateInvoice->execute(
                organizationId: $organizationId,
                quotationUuid: null,
                items: [[
                    'description' => sprintf('%s subscription (%s)', $plan->name, $plan->billingCycle),
                    'quantity' => 1,
                    'unit_price' => $plan->price,
                ]],
                currency: $plan->currency,
                daysUntilDue: 14,
                actorId: null,
                subscriptionUuid: isset($payload['subscription_uuid']) ? (string) $payload['subscription_uuid'] : null,
            );
        } catch (\Throwable $exception) {
            $logger->error('Failed to generate invoice on subscription activation.', [
                'organization_id' => $organizationId,
                'plan_code' => $planCode,
                'subscription_uuid' => $payload['subscription_uuid'] ?? null,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
