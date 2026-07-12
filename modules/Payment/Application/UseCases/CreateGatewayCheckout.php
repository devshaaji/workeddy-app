<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Application\UseCases;

use WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentMethod;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentStatus;
use WorkEddy\Modules\Payment\Domain\Gateways\GatewayCheckoutRequest;
use WorkEddy\Modules\Payment\Domain\Gateways\PaymentGatewayRegistry;
use WorkEddy\Modules\Payment\Settings\PaymentSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Shared\Support\UuidSupport;

final class CreateGatewayCheckout
{
    public function __construct(
        private readonly IPaymentRecordRepository $payments,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly PaymentSettings $settings,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function execute(
        ?string $gateway,
        int $invoiceId,
        int $organizationId,
        float $amount,
        string $currency,
        string $customerEmail,
        ?string $callbackUrl = null,
        array $metadata = [],
    ): array {
        $gatewayName = $gateway !== null && $gateway !== ''
            ? $gateway
            : $this->settings->defaultGateway();

        $driver = $this->gateways->get($gatewayName);
        $now = new \DateTimeImmutable();
        $transactionId = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $payment = $this->payments->create([
            'uuid' => UuidSupport::generate(),
            'transaction_id' => $transactionId,
            'invoice_id' => $invoiceId,
            'organization_id' => $organizationId,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'method' => PaymentMethod::ONLINE_GATEWAY,
            'status' => PaymentStatus::PENDING,
            'gateway' => $gatewayName,
            'gateway_reference' => null,
            'gateway_payload' => [],
            'notes' => null,
            'payment_date' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $checkout = $driver->initializeCheckout(new GatewayCheckoutRequest(
            transactionId: $payment->transactionId,
            amount: $payment->amount,
            currency: $payment->currency,
            customerEmail: $customerEmail,
            callbackUrl: $callbackUrl,
            metadata: array_merge($metadata, [
                'payment_uuid' => $payment->uuid,
                'invoice_id' => $payment->invoiceId,
                'organization_id' => $payment->organizationId,
            ]),
        ));

        $payment = $this->payments->update($payment->id, [
            'gateway_reference' => $checkout->gatewayReference,
            'gateway_payload' => $checkout->payload,
            'updated_at' => new \DateTimeImmutable(),
        ]);

        $this->audit->record(
            action: 'payment.gateway_checkout_created',
            entityType: 'PaymentRecord',
            entityId: $payment->uuid,
            afterState: $payment->toArray(includeInternalId: true),
            metadata: ['module' => 'Payment', 'gateway' => $gatewayName],
        );

        return [
            'payment' => $payment->toArray(),
            'gateway' => $gatewayName,
            'modal' => $checkout->modal,
        ];
    }
}
