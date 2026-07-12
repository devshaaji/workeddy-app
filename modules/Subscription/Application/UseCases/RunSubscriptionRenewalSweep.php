<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Application\UseCases;

use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Logging\ILoggerFactory;

/**
 * Finds active, auto-renewing subscriptions whose current period has ended
 * and renews each one via RenewSubscription (which publishes
 * `subscription.renewed`, picked up by GenerateInvoiceOnRenewal to bill the
 * upcoming period). Intended to be cron-triggered daily; see
 * cronjobs/subscription-renewal-sweep.php and
 * `bin/console subscription:renewal:sweep`.
 *
 * A failure renewing one subscription is logged and does not stop the
 * sweep from processing the rest.
 */
final class RunSubscriptionRenewalSweep
{
    public function __construct(
        private readonly ISubscriptionRepository $subscriptions,
        private readonly RenewSubscription $renewSubscription,
        private readonly IClock $clock,
        private readonly ILoggerFactory $loggerFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $limit = 100): array
    {
        $logger = $this->loggerFactory->channel('Subscription');
        $due = $this->subscriptions->findDueForRenewal($this->clock->now());
        $due = array_slice($due, 0, max(1, $limit));

        $renewed = [];
        $failed = [];

        foreach ($due as $subscription) {
            try {
                $updated = $this->renewSubscription->execute($subscription->uuid, actorId: null);
                $renewed[] = $updated->uuid;
            } catch (\Throwable $exception) {
                $failed[] = $subscription->uuid;
                $logger->error('Failed to renew subscription during sweep.', [
                    'subscription_uuid' => $subscription->uuid,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'inspected' => count($due),
            'renewed' => $renewed,
            'failed' => $failed,
        ];
    }
}
