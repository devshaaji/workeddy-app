<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Billing\Domain\Entities\Quotation;
use WorkEddy\Modules\Billing\Domain\Entities\QuotationStatus;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\DateFormatter;

final class DbalQuotationRepository implements IQuotationRepository
{
    public function __construct(
        private readonly Connection $conn,
        private readonly IClock $clock,
    ) {}

    public function create(array $data): Quotation
    {
        $this->conn->insert('billing_quotations', [
            'uuid' => $data['uuid'],
            'quotation_number' => $data['quotation_number'],
            'organization_id' => $data['organization_id'],
            'lead_id' => $data['lead_id'],
            'status' => $data['status']->value,
            'items' => json_encode($data['items']),
            'subtotal' => $data['subtotal'],
            'tax' => $data['tax'],
            'total' => $data['total'],
            'currency' => $data['currency'],
            'expires_at' => $data['expires_at']?->format('Y-m-d H:i:s'),
            'created_at' => $data['created_at']->format('Y-m-d H:i:s'),
            'updated_at' => $data['updated_at']->format('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->conn->lastInsertId();
        return $this->findById($id);
    }

    public function findById(int $id): ?Quotation
    {
        $row = $this->conn->fetchAssociative(
            'SELECT q.*, o.name AS organization_display_name
             FROM billing_quotations q
             LEFT JOIN organizations o ON q.organization_id = o.id
             WHERE q.id = ? AND q.archived_at IS NULL',
            [$id]
        );
        return $row ? $this->mapToEntity($row) : null;
    }

    public function findByUuid(string $uuid): ?Quotation
    {
        $row = $this->conn->fetchAssociative(
            'SELECT q.*, o.name AS organization_display_name
             FROM billing_quotations q
             LEFT JOIN organizations o ON q.organization_id = o.id
             WHERE q.uuid = ? AND q.archived_at IS NULL',
            [$uuid]
        );
        return $row ? $this->mapToEntity($row) : null;
    }

    public function update(int $id, array $data): Quotation
    {
        $updateData = [];
        foreach (['status', 'expires_at'] as $field) {
            if (array_key_exists($field, $data)) {
                if ($data[$field] instanceof QuotationStatus) {
                    $updateData[$field] = $data[$field]->value;
                } elseif ($data[$field] instanceof \DateTimeInterface) {
                    $updateData[$field] = $data[$field]->format('Y-m-d H:i:s');
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (array_key_exists('updated_at', $data)) {
            $updateData['updated_at'] = $data['updated_at']->format('Y-m-d H:i:s');
        }

        $this->conn->update('billing_quotations', $updateData, ['id' => $id]);
        return $this->findById($id);
    }

    public function archive(Quotation $quotation): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->conn->update('billing_quotations', [
            'archived_at' => $now,
            'updated_at' => $now,
        ], ['id' => $quotation->id]);
    }

    public function list(array $filters = []): array
    {
        $qb = $this->conn->createQueryBuilder()
            ->select('q.*', 'o.name AS organization_display_name')
            ->from('billing_quotations', 'q')
            ->leftJoin('q', 'organizations', 'o', 'q.organization_id = o.id')
            ->where('q.archived_at IS NULL')
            ->orderBy('q.id', 'DESC');

        if (isset($filters['organization_id'])) {
            $qb->andWhere('q.organization_id = :organizationId')->setParameter('organizationId', $filters['organization_id']);
        }

        if (isset($filters['lead_id'])) {
            $qb->andWhere('q.lead_id = :leadId')->setParameter('leadId', $filters['lead_id']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('q.status = :status')->setParameter('status', $filters['status']);
        }

        $rows = $qb->fetchAllAssociative();
        return array_map(fn(array $row) => $this->mapToEntity($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapToEntity(array $row): Quotation
    {
        return new Quotation(
            id: (int) $row['id'],
            uuid: $row['uuid'],
            quotationNumber: $row['quotation_number'],
            organizationId: (int) $row['organization_id'],
            leadId: $row['lead_id'] ? (int) $row['lead_id'] : null,
            status: QuotationStatus::from($row['status']),
            items: json_decode($row['items'] ?? '[]', true),
            subtotal: (float) $row['subtotal'],
            tax: (float) $row['tax'],
            total: (float) $row['total'],
            currency: $row['currency'],
            expiresAt: $row['expires_at'] ? DateFormatter::fromNaiveDbString($row['expires_at']) : null,
            createdAt: DateFormatter::fromNaiveDbString($row['created_at']) ?? new \DateTimeImmutable(),
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at']) ?? new \DateTimeImmutable(),
            archivedAt: DateFormatter::fromNaiveDbString($row['archived_at'] ?? null),
            organizationName: $row['organization_display_name'] !== null ? (string) $row['organization_display_name'] : null,
        );
    }
}
