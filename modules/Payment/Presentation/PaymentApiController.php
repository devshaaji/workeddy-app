<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Presentation;

use WorkEddy\Modules\Payment\Application\UseCases\CreateGatewayCheckout;
use WorkEddy\Modules\Payment\Application\UseCases\ProcessOnlinePayment;
use WorkEddy\Modules\Payment\Application\UseCases\RecordManualPayment;
use WorkEddy\Modules\Payment\Domain\Contracts\IPaymentRecordRepository;
use WorkEddy\Modules\Payment\Domain\Entities\PaymentMethod;
use WorkEddy\Modules\Payment\Domain\Gateways\PaymentGatewayRegistry;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\ForbiddenException;

final class PaymentApiController
{
    public function __construct(
        private readonly IPaymentRecordRepository $payments,
        private readonly RecordManualPayment $recordManualPayment,
        private readonly CreateGatewayCheckout $createGatewayCheckout,
        private readonly ProcessOnlinePayment $processOnlinePayment,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ISessionService $session,
    ) {}

    public function list(Request $request): Response
    {
        $filters = array_merge($request->query, $request->body, $request->json);

        $records = $this->payments->list([
            'organization_id' => $filters['organization_id'] ?? null,
            'invoice_id' => $filters['invoice_id'] ?? null,
            'status' => $filters['status'] ?? null,
        ]);

        $data = array_map(static fn($p) => $p->toArray(), $records);

        return Response::success($data);
    }

    public function recordManual(Request $request): Response
    {
        $actorId = $this->userId();
        $body = array_merge($request->query, $request->body, $request->json);

        $invoiceId = (int) ($body['invoice_id'] ?? 0);
        $organizationId = (int) ($body['organization_id'] ?? 0);
        $amount = (float) ($body['amount'] ?? 0.0);
        $currency = (string) ($body['currency'] ?? 'USD');
        $method = PaymentMethod::tryFrom((string) ($body['method'] ?? '')) ?? PaymentMethod::BANK_TRANSFER;
        $paymentDate = isset($body['payment_date']) ? new \DateTimeImmutable($body['payment_date']) : null;
        $reference = isset($body['reference']) ? (string) $body['reference'] : null;
        $notes = isset($body['notes']) ? (string) $body['notes'] : null;

        $payment = $this->recordManualPayment->execute(
            $invoiceId,
            $organizationId,
            $amount,
            $currency,
            $method,
            $paymentDate,
            $reference,
            $notes,
            $actorId
        );

        return Response::success($payment->toArray(), 'Payment recorded successfully', 201);
    }

    public function checkout(Request $request): Response
    {
        $body = array_merge($request->query, $request->body, $request->json);

        $result = $this->createGatewayCheckout->execute(
            gateway: isset($body['gateway']) ? (string) $body['gateway'] : null,
            invoiceId: (int) ($body['invoice_id'] ?? 0),
            organizationId: (int) ($body['organization_id'] ?? 0),
            amount: (float) ($body['amount'] ?? 0.0),
            currency: (string) ($body['currency'] ?? 'USD'),
            customerEmail: (string) ($body['customer_email'] ?? ''),
            callbackUrl: isset($body['callback_url']) ? (string) $body['callback_url'] : null,
            metadata: isset($body['metadata']) && is_array($body['metadata']) ? $body['metadata'] : [],
        );

        return Response::success($result, 'Gateway checkout initialized', 201);
    }

    public function webhook(Request $request): Response
    {
        // Webhooks typically authenticate differently (e.g. signature verification)
        // We will assume standard basic auth/signature is handled by middleware

        $body = array_merge($request->query, $request->body, $request->json);

        $transactionId = (string) ($body['transaction_id'] ?? '');
        $gatewayReference = (string) ($body['gateway_reference'] ?? '');
        $statusRaw = (string) ($body['status'] ?? '');
        $notes = isset($body['notes']) ? (string) $body['notes'] : null;

        $payment = $this->processOnlinePayment->handleWebhook(
            $transactionId,
            $gatewayReference,
            $statusRaw,
            $notes
        );

        return Response::success($payment->toArray(), 'Webhook processed');
    }

    public function gatewayWebhook(Request $request): Response
    {
        $gatewayName = (string) ($request->routeParam('gateway'));
        $rawPayload = json_encode($request->json !== [] ? $request->json : $request->body, JSON_THROW_ON_ERROR);
        $event = $this->gateways->get($gatewayName)->parseWebhook($rawPayload, $request->headers);
        $payment = $this->processOnlinePayment->handleGatewayWebhook($gatewayName, $event);

        return Response::success($payment->toArray(), 'Webhook processed');
    }

    private function userId(): int
    {
        $ctx = $this->session->getUserContext();
        if (!$ctx) {
            throw new ForbiddenException('Unauthenticated');
        }

        return (int) $ctx->userId;
    }
}
