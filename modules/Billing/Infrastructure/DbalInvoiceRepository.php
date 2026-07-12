<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Billing\Domain\Entities\Invoice;
use WorkEddy\Modules\Billing\Domain\Entities\InvoiceStatus;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\DateFormatter;

final class DbalInvoiceRepository implements IInvoiceRepository
{
    public function __construct(
        private readonly Connection $conn,
        private readonly IClock $clock,
    ) {}

    public function create(array $data): Invoice
    {
        $this->conn->insert('billing_invoices', [
            'uuid' => $data['uuid'],
            'invoice_number' => $data['invoice_number'],
            'organization_id' => $data['organization_id'],
            'subscription_uuid' => $data['subscription_uuid'] ?? null,
            'quotation_id' => $data['quotation_id'],
            'status' => $data['status']->value,
            'items' => json_encode($data['items']),
            'subtotal' => $data['subtotal'],
            'tax' => $data['tax'],
            'total' => $data['total'],
            'amount_paid' => $data['amount_paid'],
            'currency' => $data['currency'],
            'due_date' => $data['due_date']?->format('Y-m-d H:i:s'),
            'created_at' => $data['created_at']->format('Y-m-d H:i:s'),
            'updated_at' => $data['updated_at']->format('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->conn->lastInsertId();
        return $this->findById($id);
    }

    public function findById(int $id): ?Invoice
    {
        $row = $this->conn->fetchAssociative(
            'SELECT i.*, o.name AS organization_display_name
             FROM billing_invoices i
             LEFT JOIN organizations o ON i.organization_id = o.id
             WHERE i.id = ? AND i.archived_at IS NULL',
            [$id]
        );
        return $row ? $this->mapToEntity($row) : null;
    }

    public function findByUuid(string $uuid): ?Invoice
    {
        $row = $this->conn->fetchAssociative(
            'SELECT i.*, o.name AS organization_display_name
             FROM billing_invoices i
             LEFT JOIN organizations o ON i.organization_id = o.id
             WHERE i.uuid = ? AND i.archived_at IS NULL',
            [$uuid]
        );
        return $row ? $this->mapToEntity($row) : null;
    }

    public function update(int $id, array $data): Invoice
    {
        $updateData = [];
        foreach (['status', 'amount_paid', 'due_date'] as $field) {
            if (array_key_exists($field, $data)) {
                if ($data[$field] instanceof InvoiceStatus) {
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

        $this->conn->update('billing_invoices', $updateData, ['id' => $id]);
        return $this->findById($id);
    }

    public function archive(Invoice $invoice): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->conn->update('billing_invoices', [
            'archived_at' => $now,
            'updated_at' => $now,
        ], ['id' => $invoice->id]);
    }

    public function list(array $filters = []): array
    {
        $qb = $this->conn->createQueryBuilder()
            ->select('i.*', 'o.name AS organization_display_name')
            ->from('billing_invoices', 'i')
            ->leftJoin('i', 'organizations', 'o', 'i.organization_id = o.id')
            ->where('i.archived_at IS NULL')
            ->orderBy('i.id', 'DESC');

        if (isset($filters['organization_id'])) {
            $qb->andWhere('i.organization_id = :organizationId')->setParameter('organizationId', $filters['organization_id']);
        }

        if (isset($filters['subscription_uuid'])) {
            $qb->andWhere('i.subscription_uuid = :subscriptionUuid')->setParameter('subscriptionUuid', $filters['subscription_uuid']);
        }

        if (isset($filters['quotation_id'])) {
            $qb->andWhere('i.quotation_id = :quotationId')->setParameter('quotationId', $filters['quotation_id']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('i.status = :status')->setParameter('status', $filters['status']);
        }

        $rows = $qb->fetchAllAssociative();
        return array_map(fn(array $row) => $this->mapToEntity($row), $rows);
    }

    public function listOverdueSubscriptionInvoices(\DateTimeImmutable $asOf): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT i.*, o.name AS organization_display_name
             FROM billing_invoices i
             LEFT JOIN organizations o ON i.organization_id = o.id
             WHERE i.archived_at IS NULL
               AND i.subscription_uuid IS NOT NULL
               AND i.status IN ('unpaid', 'partial', 'overdue')
               AND i.due_date IS NOT NULL
               AND i.due_date < ?
             ORDER BY i.due_date ASC",
            [$asOf->format('Y-m-d H:i:s')]
        );

        return array_map(fn(array $row) => $this->mapToEntity($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapToEntity(array $row): Invoice
    {
        return new Invoice(
            id: (int) $row['id'],
            uuid: $row['uuid'],
            invoiceNumber: $row['invoice_number'],
            organizationId: (int) $row['organization_id'],
            quotationId: $row['quotation_id'] ? (int) $row['quotation_id'] : null,
            status: InvoiceStatus::from($row['status']),
            items: json_decode($row['items'] ?? '[]', true),
            subtotal: (float) $row['subtotal'],
            tax: (float) $row['tax'],
            total: (float) $row['total'],
            amountPaid: (float) $row['amount_paid'],
            currency: $row['currency'],
            dueDate: $row['due_date'] ? DateFormatter::fromNaiveDbString($row['due_date']) : null,
            createdAt: DateFormatter::fromNaiveDbString($row['created_at']) ?? new \DateTimeImmutable(),
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at']) ?? new \DateTimeImmutable(),
            archivedAt: DateFormatter::fromNaiveDbString($row['archived_at'] ?? null),
            organizationName: $row['organization_display_name'] !== null ? (string) $row['organization_display_name'] : null,
            subscriptionUuid: $row['subscription_uuid'] !== null ? (string) $row['subscription_uuid'] : null,
        );
    }
}
