<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Application\UseCases;

use WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentMethod;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentRecord;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentStatus;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Shared\Support\UuidSupport;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Clock\IClock;


final class RecordManualPayment
{
    public function __construct(
        private readonly IPaymentRecordRepository $payments,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly ?NotificationServiceInterface $notifications = null,
        private readonly ?OrganizationNotificationRecipientFactory $recipients = null,
    ) {}

    public function execute(
        int $invoiceId,
        int $organizationId,
        float $amount,
        string $currency,
        PaymentMethod $method,
        ?\DateTimeImmutable $paymentDate,
        ?string $reference,
        ?string $notes,
        ?int $actorId
    ): PaymentRecord {
        $payment = $this->payments->create([
            'uuid' => UuidSupport::generate(),
            'transaction_id' => 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'invoice_id' => $invoiceId,
            'organization_id' => $organizationId,
            'amount' => $amount,
            'currency' => $currency,
            'method' => $method,
            'status' => PaymentStatus::COMPLETED, // Manual payments are usually completed upon recording
            'gateway' => 'manual',
            'gateway_reference' => $reference,
            'gateway_payload' => [],
            'notes' => $notes,
            'payment_date' => $paymentDate ?? $this->clock->now(),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);

        $this->audit->record(
            action: 'payment.manual_payment_recorded',
            entityType: 'PaymentRecord',
            entityId: $payment->uuid,
            afterState: $payment->toArray(includeInternalId: true),
            actorId: $actorId !== null ? (string) $actorId : null,
            metadata: ['module' => 'Payment'],
        );

        $this->events->publish(
            'payment.completed',
            [
                'payment_uuid' => $payment->uuid,
                'invoice_id' => $payment->invoiceId,
                'amount' => $payment->amount,
            ],
            $payment->uuid
        );

        $recipient = $this->recipients?->fromOrganizationId($organizationId);
        if ($recipient !== null && $this->notifications !== null) {
            $this->notifications->send(new NotificationRequest(
                type: new NotificationType('payment_receipt'),
                recipient: $recipient,
                data: [
                    'amount' => number_format($payment->amount, 2, '.', ''),
                    'currency' => $payment->currency,
                    'reference' => $payment->gatewayReference ?? $payment->transactionId,
                    'invoice_id' => $payment->invoiceId,
                ],
                metadata: ['module' => 'Payment', 'payment_uuid' => $payment->uuid],
            ));
        }

        return $payment;
    }
}
