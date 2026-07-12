<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Application\UseCases;

use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Billing\Domain\Entities\Quotation;
use WorkEddy\Modules\Billing\Domain\Entities\QuotationStatus;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class GenerateQuotation
{
    public function __construct(
        private readonly IQuotationRepository $quotations,
        private readonly IAuditService $audit,
        private readonly ?IClock $clock = null,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function execute(
        int $organizationId,
        ?int $leadId,
        array $items,
        string $currency,
        ?int $daysUntilExpiry,
        ?int $actorId
    ): Quotation {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
        }
        $tax = $subtotal * 0.0; // Extend with Tax Module later
        $total = $subtotal + $tax;

        $now = $this->clock?->now() ?? new \DateTimeImmutable();

        $expiresAt = $daysUntilExpiry !== null
            ? $now->add(new \DateInterval("P{$daysUntilExpiry}D"))
            : null;

        $quotation = $this->quotations->create([
            'uuid' => UuidSupport::generate(),
            'quotation_number' => 'QT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'organization_id' => $organizationId,
            'lead_id' => $leadId,
            'status' => QuotationStatus::DRAFT,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit->record(
            action: 'billing.quotation.generated',
            entityType: 'Quotation',
            entityId: $quotation->uuid,
            afterState: $quotation->toArray(includeInternalId: true),
            actorId: $actorId !== null ? (string) $actorId : null,
            metadata: ['module' => 'Billing'],
        );

        return $quotation;
    }
}
