<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Domain\Contracts;

use WorkEddy\Modules\Billing\Domain\Entities\Invoice;

interface IInvoiceRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Invoice;

    public function findById(int $id): ?Invoice;

    public function findByUuid(string $uuid): ?Invoice;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Invoice;

    public function archive(Invoice $invoice): void;

    /**
     * @param array<string, mixed> $filters
     * @return array<int, Invoice>
     */
    public function list(array $filters = []): array;

    /**
     * Unpaid/partial invoices linked to a subscription (subscription_uuid
     * IS NOT NULL) whose due_date has passed as of `$asOf`. Used by
     * Subscription's dunning sweep to find subscriptions to suspend for
     * non-payment; deliberately does not depend on any status ever having
     * been flipped to `overdue`, since nothing currently does that
     * automatically.
     *
     * @return array<int, Invoice>
     */
    public function listOverdueSubscriptionInvoices(\DateTimeImmutable $asOf): array;
}
