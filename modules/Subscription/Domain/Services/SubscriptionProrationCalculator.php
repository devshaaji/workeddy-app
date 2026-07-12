<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Subscription\Domain\Services;

/**
 * Standard "unused time" proration: credits the unused portion of the old
 * plan's price for the remainder of the current billing period, charges
 * the same remaining-time fraction of the new plan's price, and nets the
 * two.
 *
 * v1 scope (see docs/subscription-rework.md): only the upgrade case (net >
 * 0) results in an invoice. Downgrades (net <= 0) are computed and
 * reported here for logging/auditing, but no credit ledger or negative
 * invoice exists yet \u2014 that, and deferring a downgrade to the next billing
 * period, are explicit Phase 2 scope. Callers must check `isUpgrade()`
 * before invoicing.
 */
final class SubscriptionProrationCalculator
{
    private function __construct(
        public readonly int $daysInPeriod,
        public readonly int $daysRemaining,
        public readonly float $creditAmount,
        public readonly float $chargeAmount,
        public readonly float $netAmount,
    ) {}

    public static function calculate(
        float $oldPlanPrice,
        float $newPlanPrice,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        \DateTimeImmutable $effectiveDate,
    ): self {
        $daysInPeriod = max(1, (int) $periodStart->diff($periodEnd)->days);

        $effectiveDate = $effectiveDate < $periodStart ? $periodStart : $effectiveDate;
        $effectiveDate = $effectiveDate > $periodEnd ? $periodEnd : $effectiveDate;
        $daysRemaining = max(0, (int) $effectiveDate->diff($periodEnd)->days);

        $fraction = $daysRemaining / $daysInPeriod;
        $creditAmount = round($oldPlanPrice * $fraction, 2);
        $chargeAmount = round($newPlanPrice * $fraction, 2);
        $netAmount = round($chargeAmount - $creditAmount, 2);

        return new self(
            daysInPeriod: $daysInPeriod,
            daysRemaining: $daysRemaining,
            creditAmount: $creditAmount,
            chargeAmount: $chargeAmount,
            netAmount: $netAmount,
        );
    }

    public function isUpgrade(): bool
    {
        return $this->netAmount > 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'days_in_period' => $this->daysInPeriod,
            'days_remaining' => $this->daysRemaining,
            'credit_amount' => $this->creditAmount,
            'charge_amount' => $this->chargeAmount,
            'net_amount' => $this->netAmount,
            'is_upgrade' => $this->isUpgrade(),
        ];
    }
}
