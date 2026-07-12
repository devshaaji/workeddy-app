<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Application\UseCases;

use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Billing\Domain\Entities\Invoice;
use WorkEddy\Modules\Billing\Domain\Entities\InvoiceStatus;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;
use WorkEddy\Shared\Exceptions\ValidationException;

final class GenerateInvoice
{
    public function __construct(
        private readonly IInvoiceRepository $invoices,
        private readonly IQuotationRepository $quotations,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
        private readonly ?NotificationServiceInterface $notifications = null,
        private readonly ?OrganizationNotificationRecipientFactory $recipients = null,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function execute(
        int $organizationId,
        ?string $quotationUuid,
        array $items,
        string $currency,
        ?int $daysUntilDue,
        ?int $actorId,
        ?string $subscriptionUuid = null,
    ): Invoice {
        $quotationId = null;

        if ($quotationUuid !== null) {
            $quotation = $this->quotations->findByUuid($quotationUuid);
            if ($quotation === null) {
                throw new ValidationException(['quotation' => 'Quotation not found']);
            }
            $quotationId = $quotation->id;

            // If items are not passed explicitly, copy from quotation
            if (empty($items)) {
                $items = $quotation->items;
            }
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
        }
        $tax = $subtotal * 0.0;
        $total = $subtotal + $tax;

        $dueDate = $daysUntilDue !== null
            ? ($this->clock->now())->add(new \DateInterval("P{$daysUntilDue}D"))
            : null;

        $invoice = $this->invoices->create([
            'uuid' => UuidSupport::generate(),
            'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'organization_id' => $organizationId,
            'subscription_uuid' => $subscriptionUuid,
            'quotation_id' => $quotationId,
            'status' => InvoiceStatus::UNPAID,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'amount_paid' => 0.0,
            'currency' => $currency,
            'due_date' => $dueDate,
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);

        $this->audit->record(
            action: 'billing.invoice.generated',
            entityType: 'Invoice',
            entityId: $invoice->uuid,
            afterState: $invoice->toArray(includeInternalId: true),
            actorId: $actorId !== null ? (string) $actorId : null,
            metadata: ['module' => 'Billing'],
        );

        $recipient = $this->recipients?->fromOrganizationId($organizationId);
        if ($recipient !== null && $this->notifications !== null) {
            $this->notifications->send(new NotificationRequest(
                type: new NotificationType('invoice_created'),
                recipient: $recipient,
                data: [
                    'invoice_number' => $invoice->invoiceNumber,
                    'amount' => number_format($invoice->total, 2, '.', ''),
                    'currency' => $invoice->currency,
                    'due_date' => $invoice->dueDate?->format('Y-m-d'),
                    'organization_id' => $invoice->organizationId,
                    'subscription_uuid' => $invoice->subscriptionUuid,
                ],
                metadata: ['module' => 'Billing', 'invoice_uuid' => $invoice->uuid],
            ));
        }

        return $invoice;
    }
}
