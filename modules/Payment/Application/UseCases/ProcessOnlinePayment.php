<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Application\UseCases;

use WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository;
use WorkEddy\Modules\Notification\Application\OrganizationNotificationRecipientFactory;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentRecord;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentStatus;
use WorkEddy\Modules\Payment\Domain\Gateways\GatewayWebhookEvent;
use WorkEddy\Modules\Payment\Domain\Gateways\PaymentGatewayRegistry;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Platform\Events\EventPublisherInterface;

final class ProcessOnlinePayment
{
    public function __construct(
        private readonly IPaymentRecordRepository $payments,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly ?PaymentGatewayRegistry $gateways = null,
        private readonly ?NotificationServiceInterface $notifications = null,
        private readonly ?OrganizationNotificationRecipientFactory $recipients = null,
    ) {}

    public function handleGatewayWebhook(string $gateway, GatewayWebhookEvent $event): PaymentRecord
    {
        $records = $this->payments->list([
            'transaction_id' => $event->transactionId,
            'gateway' => $gateway,
        ]);

        if (empty($records)) {
            throw new ValidationException(['payment' => 'Payment record not found']);
        }

        if ($this->gateways !== null && $event->status === PaymentStatus::COMPLETED) {
            $verified = $this->gateways->get($gateway)->verifyTransaction($event->transactionId);
            if ($verified->status !== PaymentStatus::COMPLETED) {
                throw new ValidationException(['payment' => 'Gateway transaction could not be verified.']);
            }
        }

        return $this->applyStatusUpdate(
            $records[0],
            $event->status,
            $event->gatewayReference,
            $event->notes,
            array_merge($records[0]->gatewayPayload, ['webhook' => $event->payload]),
            ['module' => 'Payment', 'webhook' => true, 'gateway' => $gateway],
        );
    }

    public function handleWebhook(
        string $transactionId,
        string $gatewayReference,
        string $statusRaw,
        ?string $notes
    ): PaymentRecord {
        // Simple search for the pending payment by transaction ID
        // Assuming we extended repository with findByTransactionId, for now we will just use a generic list/search
        $filters = ['transaction_id' => $transactionId];
        $records = $this->payments->list($filters);

        if (empty($records)) {
            throw new ValidationException(['payment' => 'Payment record not found']);
        }
        $payment = $records[0];

        $newStatus = match (strtolower($statusRaw)) {
            'success', 'completed' => PaymentStatus::COMPLETED,
            'failed', 'declined' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };

        return $this->applyStatusUpdate(
            $payment,
            $newStatus,
            $gatewayReference,
            $notes,
            $payment->gatewayPayload,
            ['module' => 'Payment', 'webhook' => true],
        );
    }

    /**
     * @param array<string, mixed> $gatewayPayload
     * @param array<string, mixed> $metadata
     */
    private function applyStatusUpdate(
        PaymentRecord $payment,
        PaymentStatus $newStatus,
        ?string $gatewayReference,
        ?string $notes,
        array $gatewayPayload,
        array $metadata,
    ): PaymentRecord {
        if ($payment->status === $newStatus && $payment->gatewayReference === $gatewayReference) {
            return $payment;
        }

        $beforeState = $payment->toArray(includeInternalId: true);

        $updated = $this->payments->update($payment->id, [
            'status' => $newStatus,
            'gateway_reference' => $gatewayReference,
            'gateway_payload' => $gatewayPayload,
            'notes' => $notes !== null ? $payment->notes . "\n\nWebhook Update: " . $notes : $payment->notes,
            'updated_at' => $this->clock->now(),
        ]);

        $this->audit->record(
            action: 'payment.online_status_updated',
            entityType: 'PaymentRecord',
            entityId: $payment->uuid,
            beforeState: $beforeState,
            afterState: $updated->toArray(includeInternalId: true),
            metadata: $metadata,
        );

        if ($newStatus === PaymentStatus::COMPLETED) {
            $this->events->publish(
                'payment.completed',
                [
                    'payment_uuid' => $updated->uuid,
                    'invoice_id' => $updated->invoiceId,
                    'amount' => $updated->amount,
                ],
                $updated->uuid
            );

            $recipient = $this->recipients?->fromOrganizationId($updated->organizationId);
            if ($recipient !== null && $this->notifications !== null) {
                $this->notifications->send(new NotificationRequest(
                    type: new NotificationType('payment_receipt'),
                    recipient: $recipient,
                    data: [
                        'amount' => number_format($updated->amount, 2, '.', ''),
                        'currency' => $updated->currency,
                        'reference' => $updated->gatewayReference ?? $updated->transactionId,
                        'invoice_id' => $updated->invoiceId,
                    ],
                    metadata: ['module' => 'Payment', 'payment_uuid' => $updated->uuid],
                ));
            }
        }

        return $updated;
    }
}
