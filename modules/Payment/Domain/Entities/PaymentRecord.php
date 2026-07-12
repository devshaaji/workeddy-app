<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Domain\Entities;

final class PaymentRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $transactionId,
        public readonly int $invoiceId,
        public readonly int $organizationId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly PaymentMethod $method,
        public readonly PaymentStatus $status,
        public readonly ?string $gateway,
        public readonly ?string $gatewayReference,
        /** @var array<string, mixed> */
        public readonly array $gatewayPayload,
        public readonly ?string $notes,
        public readonly \DateTimeImmutable $paymentDate,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?string $organizationName = null,
        public readonly ?string $invoiceNumber = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'uuid' => $this->uuid,
            'transaction_id' => $this->transactionId,
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'organization_id' => $this->organizationId,
            'organization_name' => $this->organizationName,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'gateway' => $this->gateway,
            'gateway_reference' => $this->gatewayReference,
            'gateway_payload' => $this->gatewayPayload,
            'notes' => $this->notes,
            'payment_date' => $this->paymentDate->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];

        if ($includeInternalId) {
            $data['id'] = $this->id;
        }

        return $data;
    }
}
