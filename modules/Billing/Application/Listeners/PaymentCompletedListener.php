<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Application\Listeners;

use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Billing\Domain\Entities\InvoiceStatus;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Events\IAsyncEventListener;
use WorkEddy\Platform\Clock\IClock;

final class PaymentCompletedListener implements IAsyncEventListener
{
    public function __construct(
        private readonly IInvoiceRepository $invoices,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
    ) {}

    public function __invoke(array $eventPayload): void
    {
        $invoiceId = $eventPayload['invoice_id'] ?? null;
        if ($invoiceId === null) {
            return;
        }

        $invoice = $this->invoices->findById((int) $invoiceId);
        if ($invoice === null) {
            return;
        }

        $amount = (float) ($eventPayload['amount'] ?? 0.0);
        $amountPaid = $invoice->amountPaid + $amount;

        $newStatus = InvoiceStatus::PARTIAL;
        if (round($amountPaid, 2) >= round($invoice->total, 2)) {
            $newStatus = InvoiceStatus::PAID;
        }

        $this->invoices->update($invoice->id, [
            'amount_paid' => $amountPaid,
            'status' => $newStatus,
            'updated_at' => $this->clock->now(),
        ]);

        if ($newStatus === InvoiceStatus::PAID) {
            $this->events->publish(
                'invoice.paid',
                [
                    'invoice_id' => $invoice->id,
                    'organization_id' => $invoice->organizationId,
                    'subscription_uuid' => $invoice->subscriptionUuid,
                    'amount' => $invoice->total,
                ],
                $eventPayload['payment_uuid'] ?? (string) $invoice->id
            );
        }
    }
}
