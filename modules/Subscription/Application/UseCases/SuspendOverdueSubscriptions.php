<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Modules\Subscription\Settings\SubscriptionSettings;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Logging\ILoggerFactory;

/**
 * Dunning sweep: finds subscription-linked invoices that are unpaid past
 * their due date and suspends the subscription for non-payment, unless
 * `SubscriptionSettings::autoSuspendOnExpiry()` is turned off. Pairs with
 * RunSubscriptionRenewalSweep \u2014 renewal is optimistic (extends the period
 * and invoices immediately), this sweep is the backstop that walks it back
 * if payment never arrives. Intended to be cron-triggered daily; see
 * cronjobs/subscription-dunning-sweep.php and
 * `bin/console subscription:dunning:sweep`.
 */
final class SuspendOverdueSubscriptions
{
    public function __construct(
        private readonly IInvoiceRepository $invoices,
        private readonly ISubscriptionRepository $subscriptions,
        private readonly SuspendSubscription $suspendSubscription,
        private readonly SubscriptionSettings $settings,
        private readonly IClock $clock,
        private readonly ILoggerFactory $loggerFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $limit = 100): array
    {
        $logger = $this->loggerFactory->channel('Subscription');

        if (!$this->settings->autoSuspendOnExpiry()) {
            return ['inspected' => 0, 'suspended' => [], 'failed' => [], 'skipped_reason' => 'auto_suspend_on_expiry disabled'];
        }

        $overdueInvoices = $this->invoices->listOverdueSubscriptionInvoices($this->clock->now());
        $overdueInvoices = array_slice($overdueInvoices, 0, max(1, $limit));

        $suspended = [];
        $failed = [];
        $seen = [];

        foreach ($overdueInvoices as $invoice) {
            $subscriptionUuid = $invoice->subscriptionUuid;
            if ($subscriptionUuid === null || isset($seen[$subscriptionUuid])) {
                continue;
            }
            $seen[$subscriptionUuid] = true;

            $subscription = $this->subscriptions->findSubscriptionByUuid($subscriptionUuid);
            if ($subscription === null || $subscription->status !== SubscriptionStatus::ACTIVE) {
                // Already suspended/cancelled/expired, or not found \u2014 nothing to do.
                continue;
            }

            try {
                $this->suspendSubscription->execute(
                    $subscriptionUuid,
                    reason: sprintf('Payment overdue on invoice %s.', $invoice->invoiceNumber),
                    actorId: null,
                );
                $suspended[] = $subscriptionUuid;
            } catch (\Throwable $exception) {
                $failed[] = $subscriptionUuid;
                $logger->error('Failed to suspend overdue subscription during dunning sweep.', [
                    'subscription_uuid' => $subscriptionUuid,
                    'invoice_uuid' => $invoice->uuid,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'inspected' => count($overdueInvoices),
            'suspended' => $suspended,
            'failed' => $failed,
        ];
    }
}
