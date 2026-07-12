<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Application\UseCases;

use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Billing\Domain\Entities\Quotation;
use WorkEddy\Modules\Billing\Domain\Entities\QuotationStatus;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\ValidationException;

final class AcceptQuotation
{
    public function __construct(
        private readonly IQuotationRepository $quotations,
        private readonly IAuditService $audit,
        private readonly ?IClock $clock = null,
    ) {}

    public function execute(string $uuid, ?int $actorId): Quotation
    {
        $quotation = $this->quotations->findByUuid($uuid);

        if ($quotation === null) {
            throw new ValidationException(['quotation' => 'Quotation not found.']);
        }

        if ($quotation->status !== QuotationStatus::SENT && $quotation->status !== QuotationStatus::DRAFT) {
            throw new ValidationException(['quotation' => 'Only pending quotations can be accepted.']);
        }

        $beforeState = $quotation->toArray(includeInternalId: true);

        $updated = $this->quotations->update($quotation->id, [
            'status' => QuotationStatus::ACCEPTED,
            'updated_at' => $this->clock?->now() ?? new \DateTimeImmutable(),
        ]);

        $this->audit->record(
            action: 'billing.quotation.accepted',
            entityType: 'Quotation',
            entityId: $quotation->uuid,
            beforeState: $beforeState,
            afterState: $updated->toArray(includeInternalId: true),
            actorId: $actorId !== null ? (string) $actorId : null,
            metadata: ['module' => 'Billing'],
        );

        return $updated;
    }
}
