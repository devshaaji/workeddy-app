<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentMethod;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentRecord;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentStatus;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\DateFormatter;

final class DbalPaymentRecordRepository implements IPaymentRecordRepository
{
    public function __construct(
        private readonly Connection $conn,
        private readonly IClock $clock,
    ) {}

    public function create(array $data): PaymentRecord
    {
        $this->conn->insert('payment_records', [
            'uuid' => $data['uuid'],
            'transaction_id' => $data['transaction_id'],
            'invoice_id' => $data['invoice_id'],
            'organization_id' => $data['organization_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'method' => $data['method']->value,
            'status' => $data['status']->value,
            'gateway' => $data['gateway'] ?? null,
            'gateway_reference' => $data['gateway_reference'],
            'gateway_payload' => json_encode($data['gateway_payload'] ?? [], JSON_THROW_ON_ERROR),
            'notes' => $data['notes'],
            'payment_date' => $data['payment_date']->format('Y-m-d H:i:s'),
            'created_at' => $data['created_at']->format('Y-m-d H:i:s'),
            'updated_at' => $data['updated_at']->format('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->conn->lastInsertId();
        return $this->findById($id);
    }

    public function findById(int $id): ?PaymentRecord
    {
        $row = $this->conn->fetchAssociative(
            'SELECT p.*, i.invoice_number, o.name AS organization_name
             FROM payment_records p
             LEFT JOIN billing_invoices i ON p.invoice_id = i.id
             LEFT JOIN organizations o ON p.organization_id = o.id
             WHERE p.id = ?',
            [$id]
        );
        return $row ? $this->mapToEntity($row) : null;
    }

    public function findByUuid(string $uuid): ?PaymentRecord
    {
        $row = $this->conn->fetchAssociative(
            'SELECT p.*, i.invoice_number, o.name AS organization_name
             FROM payment_records p
             LEFT JOIN billing_invoices i ON p.invoice_id = i.id
             LEFT JOIN organizations o ON p.organization_id = o.id
             WHERE p.uuid = ?',
            [$uuid]
        );
        return $row ? $this->mapToEntity($row) : null;
    }

    public function update(int $id, array $data): PaymentRecord
    {
        $updateData = [];
        foreach (['status', 'gateway', 'gateway_reference', 'gateway_payload', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                if ($data[$field] instanceof PaymentStatus) {
                    $updateData[$field] = $data[$field]->value;
                } elseif ($field === 'gateway_payload') {
                    $updateData[$field] = json_encode($data[$field], JSON_THROW_ON_ERROR);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (array_key_exists('updated_at', $data)) {
            $updateData['updated_at'] = $data['updated_at']->format('Y-m-d H:i:s');
        }

        $this->conn->update('payment_records', $updateData, ['id' => $id]);
        return $this->findById($id);
    }

    public function list(array $filters = []): array
    {
        $qb = $this->conn->createQueryBuilder()
            ->select('p.*', 'i.invoice_number', 'o.name AS organization_name')
            ->from('payment_records', 'p')
            ->leftJoin('p', 'billing_invoices', 'i', 'p.invoice_id = i.id')
            ->leftJoin('p', 'organizations', 'o', 'p.organization_id = o.id')
            ->orderBy('p.id', 'DESC');

        if (isset($filters['organization_id'])) {
            $qb->andWhere('p.organization_id = :organizationId')->setParameter('organizationId', $filters['organization_id']);
        }

        if (isset($filters['invoice_id'])) {
            $qb->andWhere('p.invoice_id = :invoiceId')->setParameter('invoiceId', $filters['invoice_id']);
        }

        if (isset($filters['transaction_id'])) {
            $qb->andWhere('p.transaction_id = :transactionId')->setParameter('transactionId', $filters['transaction_id']);
        }

        if (isset($filters['gateway'])) {
            $qb->andWhere('p.gateway = :gateway')->setParameter('gateway', $filters['gateway']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('p.status = :status')->setParameter('status', $filters['status']);
        }

        $rows = $qb->fetchAllAssociative();
        return array_map(fn(array $row) => $this->mapToEntity($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapToEntity(array $row): PaymentRecord
    {
        return new PaymentRecord(
            id: (int) $row['id'],
            uuid: $row['uuid'],
            transactionId: $row['transaction_id'],
            invoiceId: (int) $row['invoice_id'],
            organizationId: (int) $row['organization_id'],
            amount: (float) $row['amount'],
            currency: $row['currency'],
            method: PaymentMethod::from($row['method']),
            status: PaymentStatus::from($row['status']),
            gateway: $row['gateway'] ?? null,
            gatewayReference: $row['gateway_reference'],
            gatewayPayload: $this->decodeGatewayPayload($row['gateway_payload'] ?? null),
            notes: $row['notes'],
            paymentDate: DateFormatter::fromNaiveDbString($row['payment_date']) ?? new \DateTimeImmutable(),
            createdAt: DateFormatter::fromNaiveDbString($row['created_at']) ?? new \DateTimeImmutable(),
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at']) ?? new \DateTimeImmutable(),
            organizationName: isset($row['organization_name']) ? (string) $row['organization_name'] : null,
            invoiceNumber: isset($row['invoice_number']) ? (string) $row['invoice_number'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeGatewayPayload(mixed $payload): array
    {
        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
