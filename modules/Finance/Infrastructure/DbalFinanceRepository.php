<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Infrastructure;

use WorkEddy\Modules\Finance\Domain\Contracts\IFinanceRepository;
use WorkEddy\Modules\Finance\Domain\Entities\ExpenseRecord;
use WorkEddy\Modules\Finance\Domain\Entities\IncomeRecord;
use WorkEddy\Modules\Finance\Domain\Entities\PayrollSummary;
use Doctrine\DBAL\Connection;

final class DbalFinanceRepository implements IFinanceRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function createIncome(array $data): IncomeRecord
    {
        $uuid = (string) ($data['uuid'] ?? $this->generateUuid());
        $now = (string) ($data['created_at'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->connection->insert('finance_income_records', [
            'uuid' => $uuid,
            'source_type' => (string) $data['source_type'],
            'reference_number' => (string) $data['reference_number'],
            'category' => (string) $data['category'],
            'amount' => (float) $data['amount'],
            'currency' => (string) $data['currency'],
            'description' => (string) $data['description'],
            'created_at' => $now,
            'updated_at' => (string) ($data['updated_at'] ?? $now),
        ]);

        return $this->mapIncome($this->connection->fetchAssociative('SELECT * FROM finance_income_records WHERE uuid = ?', [$uuid]));
    }

    public function findIncomeByUuid(string $uuid): ?IncomeRecord
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM finance_income_records WHERE uuid = ? AND archived_at IS NULL', [$uuid]);

        return $row === false ? null : $this->mapIncome($row);
    }

    public function updateIncome(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $payload = $this->only($data, [
            'source_type',
            'reference_number',
            'category',
            'amount',
            'currency',
            'description',
        ]);
        if ($payload === []) {
            return;
        }

        $payload['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->update('finance_income_records', $payload, ['id' => $id]);
    }

    public function archiveIncome(int $id): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->update('finance_income_records', [
            'archived_at' => $now,
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function createExpense(array $data): ExpenseRecord
    {
        $uuid = (string) ($data['uuid'] ?? $this->generateUuid());
        $now = (string) ($data['created_at'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->connection->insert('finance_expense_records', [
            'uuid' => $uuid,
            'reference_number' => (string) $data['reference_number'],
            'category' => (string) $data['category'],
            'amount' => (float) $data['amount'],
            'currency' => (string) $data['currency'],
            'description' => (string) $data['description'],
            'created_at' => $now,
            'updated_at' => (string) ($data['updated_at'] ?? $now),
        ]);

        return $this->mapExpense($this->connection->fetchAssociative('SELECT * FROM finance_expense_records WHERE uuid = ?', [$uuid]));
    }

    public function findExpenseByUuid(string $uuid): ?ExpenseRecord
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM finance_expense_records WHERE uuid = ? AND archived_at IS NULL', [$uuid]);

        return $row === false ? null : $this->mapExpense($row);
    }

    public function updateExpense(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $payload = $this->only($data, [
            'reference_number',
            'category',
            'amount',
            'currency',
            'description',
        ]);
        if ($payload === []) {
            return;
        }

        $payload['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->update('finance_expense_records', $payload, ['id' => $id]);
    }

    public function archiveExpense(int $id): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->update('finance_expense_records', [
            'archived_at' => $now,
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function upsertPayrollSummary(array $data): PayrollSummary
    {
        $existing = $this->connection->fetchAssociative('SELECT * FROM finance_payroll_summaries WHERE period_key = ?', [(string) $data['period_key']]);
        $now = (string) ($data['updated_at'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $payload = [
            'gross_amount' => (float) $data['gross_amount'],
            'net_amount' => (float) $data['net_amount'],
            'currency' => (string) $data['currency'],
            'employee_count' => (int) $data['employee_count'],
            'updated_at' => $now,
        ];

        if ($existing === false) {
            $payload['uuid'] = (string) ($data['uuid'] ?? $this->generateUuid());
            $payload['period_key'] = (string) $data['period_key'];
            $payload['created_at'] = (string) ($data['created_at'] ?? $now);
            $this->connection->insert('finance_payroll_summaries', $payload);
        } else {
            $this->connection->update('finance_payroll_summaries', $payload, ['period_key' => (string) $data['period_key']]);
        }

        return $this->mapPayrollSummary($this->connection->fetchAssociative('SELECT * FROM finance_payroll_summaries WHERE period_key = ?', [(string) $data['period_key']]));
    }

    public function listIncome(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('finance_income_records')
            ->where('archived_at IS NULL')
            ->orderBy('id', 'DESC');
        if (isset($filters['category'])) {
            $qb->andWhere('category = :category')->setParameter('category', (string) $filters['category']);
        }

        return array_map(fn(array $row): IncomeRecord => $this->mapIncome($row), $qb->fetchAllAssociative());
    }

    public function listExpenses(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('finance_expense_records')
            ->where('archived_at IS NULL')
            ->orderBy('id', 'DESC');
        if (isset($filters['category'])) {
            $qb->andWhere('category = :category')->setParameter('category', (string) $filters['category']);
        }

        return array_map(fn(array $row): ExpenseRecord => $this->mapExpense($row), $qb->fetchAllAssociative());
    }

    public function listPayrollSummaries(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()->select('*')->from('finance_payroll_summaries')->orderBy('period_key', 'DESC');
        if (isset($filters['period_key'])) {
            $qb->andWhere('period_key = :periodKey')->setParameter('periodKey', (string) $filters['period_key']);
        }

        return array_map(fn(array $row): PayrollSummary => $this->mapPayrollSummary($row), $qb->fetchAllAssociative());
    }

    public function summary(): array
    {
        return [
            'income_total' => (float) $this->connection->fetchOne('SELECT COALESCE(SUM(amount), 0) FROM finance_income_records WHERE archived_at IS NULL'),
            'expense_total' => (float) $this->connection->fetchOne('SELECT COALESCE(SUM(amount), 0) FROM finance_expense_records WHERE archived_at IS NULL'),
            'payroll_gross_total' => (float) $this->connection->fetchOne('SELECT COALESCE(SUM(gross_amount), 0) FROM finance_payroll_summaries'),
            'payroll_net_total' => (float) $this->connection->fetchOne('SELECT COALESCE(SUM(net_amount), 0) FROM finance_payroll_summaries'),
            'payroll_periods' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM finance_payroll_summaries'),
        ];
    }

    private function mapIncome(array $row): IncomeRecord
    {
        return new IncomeRecord((int) $row['id'], (string) $row['uuid'], (string) $row['source_type'], (string) $row['reference_number'], (string) $row['category'], (float) $row['amount'], (string) $row['currency'], (string) $row['description'], (string) $row['created_at'], (string) $row['updated_at']);
    }

    private function mapExpense(array $row): ExpenseRecord
    {
        return new ExpenseRecord((int) $row['id'], (string) $row['uuid'], (string) $row['reference_number'], (string) $row['category'], (float) $row['amount'], (string) $row['currency'], (string) $row['description'], (string) $row['created_at'], (string) $row['updated_at']);
    }

    private function mapPayrollSummary(array $row): PayrollSummary
    {
        return new PayrollSummary((int) $row['id'], (string) ($row['uuid'] ?? ''), (string) $row['period_key'], (float) $row['gross_amount'], (float) $row['net_amount'], (string) $row['currency'], (int) $row['employee_count'], (string) $row['created_at'], (string) $row['updated_at']);
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private function only(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }
}
