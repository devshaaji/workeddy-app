<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Payment\Infrastructure\Gateways;

use WorkEddy\Modules\Payment\Domain\Entities\PaymentStatus;
use WorkEddy\Modules\Payment\Domain\Gateways\GatewayCheckoutRequest;
use WorkEddy\Modules\Payment\Domain\Gateways\GatewayCheckoutResponse;
use WorkEddy\Modules\Payment\Domain\Gateways\GatewayWebhookEvent;
use WorkEddy\Modules\Payment\Domain\Gateways\PaymentGatewayInterface;
use WorkEddy\Shared\Exceptions\ValidationException;
use Symfony\Component\HttpClient\HttpClient;

final class PaystackPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?object $httpClient = null,
    ) {}

    public function name(): string
    {
        return 'paystack';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function initializeCheckout(GatewayCheckoutRequest $request): GatewayCheckoutResponse
    {
        $payload = [
            'email' => $request->customerEmail,
            'amount' => (int) round($request->amount * 100),
            'currency' => strtoupper($request->currency),
            'reference' => $request->transactionId,
            'metadata' => $request->metadata,
        ];

        if ($request->callbackUrl !== null && $request->callbackUrl !== '') {
            $payload['callback_url'] = $request->callbackUrl;
        }

        $response = $this->client()->request('POST', $this->baseUrl() . '/transaction/initialize', [
            'headers' => $this->headers(),
            'json' => $payload,
        ])->toArray();

        if (($response['status'] ?? false) !== true || !isset($response['data']) || !is_array($response['data'])) {
            throw new ValidationException(['gateway' => 'Paystack checkout initialization failed.']);
        }

        $data = $response['data'];

        return new GatewayCheckoutResponse(
            gateway: $this->name(),
            gatewayReference: (string) ($data['reference'] ?? $request->transactionId),
            modal: [
                'authorization_url' => (string) ($data['authorization_url'] ?? ''),
                'access_code' => (string) ($data['access_code'] ?? ''),
                'reference' => (string) ($data['reference'] ?? $request->transactionId),
                'public_key' => (string) ($this->config['public_key'] ?? ''),
            ],
            payload: [
                'authorization_url' => (string) ($data['authorization_url'] ?? ''),
                'access_code' => (string) ($data['access_code'] ?? ''),
                'reference' => (string) ($data['reference'] ?? $request->transactionId),
            ],
        );
    }

    public function parseWebhook(string $rawPayload, array $headers): GatewayWebhookEvent
    {
        $signature = $this->header($headers, 'x-paystack-signature');
        $expected = hash_hmac('sha512', $rawPayload, $this->webhookSecret());

        if ($signature === null || !hash_equals($expected, $signature)) {
            throw new ValidationException(['signature' => 'Invalid Paystack webhook signature.']);
        }

        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            throw new ValidationException(['payload' => 'Invalid Paystack webhook payload.']);
        }

        $event = (string) ($decoded['event'] ?? '');
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $reference = (string) ($data['reference'] ?? '');

        return new GatewayWebhookEvent(
            gateway: $this->name(),
            transactionId: $reference,
            gatewayReference: isset($data['id']) ? (string) $data['id'] : $reference,
            status: $event === 'charge.success' ? PaymentStatus::COMPLETED : PaymentStatus::PENDING,
            notes: $event !== '' ? 'Paystack ' . $event : null,
            payload: $decoded,
        );
    }

    public function verifyTransaction(string $transactionId): GatewayWebhookEvent
    {
        $response = $this->client()->request('GET', $this->baseUrl() . '/transaction/verify/' . rawurlencode($transactionId), [
            'headers' => $this->headers(),
        ])->toArray();

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $status = strtolower((string) ($data['status'] ?? ''));

        return new GatewayWebhookEvent(
            gateway: $this->name(),
            transactionId: (string) ($data['reference'] ?? $transactionId),
            gatewayReference: isset($data['id']) ? (string) $data['id'] : null,
            status: $status === 'success' ? PaymentStatus::COMPLETED : PaymentStatus::PENDING,
            notes: 'Paystack transaction verification',
            payload: $response,
        );
    }

    private function client(): object
    {
        return $this->httpClient ?? HttpClient::create();
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . (string) ($this->config['secret_key'] ?? ''),
            'Content-Type' => 'application/json',
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://api.paystack.co'), '/');
    }

    private function webhookSecret(): string
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');

        return $secret !== '' ? $secret : (string) ($this->config['secret_key'] ?? '');
    }

    /**
     * @param array<string, string> $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return $value;
            }
        }

        return null;
    }
}
