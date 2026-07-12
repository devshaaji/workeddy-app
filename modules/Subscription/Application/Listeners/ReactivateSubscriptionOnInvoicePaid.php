<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\Listeners;

use WorkEddy\Modules\Subscription\Application\UseCases\ReactivateSubscription;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Platform\Events\IAsyncEventListener;
use WorkEddy\Platform\Logging\ILoggerFactory;

/**
 * Reacts to Billing's `invoice.paid` event by reactivating a suspended
 * subscription once its outstanding invoice is settled. Relies on
 * `billing_invoices.subscription_uuid` (see
 * Version20260707160000_AddSubscriptionLinkageToBillingInvoices) to find
 * the subscription directly, rather than guessing from an organization key.
 *
 * Scope note: this only handles the "suspended \u2192 paid \u2192 active" path.
 * It intentionally does not call RenewSubscription here, since Billing has
 * no way yet to distinguish an initial activation invoice from a
 * recurring renewal invoice; wiring `subscription.renewed` \u2194 invoicing is
 * tracked as a separate follow-up (see docs/subscription-rework.md \u00a76.2).
 */
final class ReactivateSubscriptionOnInvoicePaid implements IAsyncEventListener
{
    public function __construct(
        private readonly ISubscriptionRepository $subscriptions,
        private readonly ReactivateSubscription $reactivateSubscription,
        private readonly ILoggerFactory $loggerFactory,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload): void
    {
        $subscriptionUuid = isset($payload['subscription_uuid']) ? (string) $payload['subscription_uuid'] : '';
        if ($subscriptionUuid === '') {
            return;
        }

        $subscription = $this->subscriptions->findSubscriptionByUuid($subscriptionUuid);
        if ($subscription === null || $subscription->status !== SubscriptionStatus::SUSPENDED) {
            return;
        }

        try {
            $this->reactivateSubscription->execute($subscriptionUuid, actorId: null);
        } catch (\Throwable $exception) {
            $this->loggerFactory->channel('Subscription')->error('Failed to auto-reactivate subscription after invoice payment.', [
                'subscription_uuid' => $subscriptionUuid,
                'invoice_id' => $payload['invoice_id'] ?? null,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
