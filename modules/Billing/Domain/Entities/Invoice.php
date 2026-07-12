<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Domain\Entities;

/**
 * Billed party is always an Organization (there is no Customer module in
 * this codebase to bill against; see docs/subscription-rework.md \u00a76.2 and
 * modules-map.md \u00a72.10). `organizationId` is a numeric Organization id;
 * `organizationName` is a denormalized display copy resolved at read time.
 */
final class Invoice
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $invoiceNumber,
        public readonly int $organizationId,
        public readonly ?int $quotationId,
        public readonly InvoiceStatus $status,
        public readonly array $items,
        public readonly float $subtotal,
        public readonly float $tax,
        public readonly float $total,
        public readonly float $amountPaid,
        public readonly string $currency,
        public readonly ?\DateTimeImmutable $dueDate,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?\DateTimeImmutable $archivedAt = null,
        public readonly ?string $organizationName = null,
        public readonly ?string $subscriptionUuid = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'uuid' => $this->uuid,
            'invoice_number' => $this->invoiceNumber,
            'organization_id' => $this->organizationId,
            'organization_name' => $this->organizationName,
            'subscription_uuid' => $this->subscriptionUuid,
            'quotation_id' => $this->quotationId,
            'status' => $this->status->value,
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'amount_paid' => $this->amountPaid,
            'balance' => round($this->total - $this->amountPaid, 2),
            'currency' => $this->currency,
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
            'archived_at' => $this->archivedAt?->format('c'),
        ];

        if ($includeInternalId) {
            $data['id'] = $this->id;
        }

        return $data;
    }
}
