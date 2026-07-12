<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Domain\Entities;

/**
 * Billed party is always an Organization (see Invoice.php doc comment for
 * rationale). `leadId` remains a loose, unvalidated int for now \u2014 there is
 * no Lead/CRM module in this codebase either, but that is out of scope for
 * this cleanup.
 */
final class Quotation
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $quotationNumber,
        public readonly int $organizationId,
        public readonly ?int $leadId,
        public readonly QuotationStatus $status,
        public readonly array $items,
        public readonly float $subtotal,
        public readonly float $tax,
        public readonly float $total,
        public readonly string $currency,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?\DateTimeImmutable $archivedAt = null,
        public readonly ?string $organizationName = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'uuid' => $this->uuid,
            'quotation_number' => $this->quotationNumber,
            'organization_id' => $this->organizationId,
            'organization_name' => $this->organizationName,
            'lead_id' => $this->leadId,
            'status' => $this->status->value,
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'currency' => $this->currency,
            'expires_at' => $this->expiresAt?->format('c'),
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
