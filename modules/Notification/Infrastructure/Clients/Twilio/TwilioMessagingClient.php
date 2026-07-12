<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients\Twilio;

use WorkEddy\Modules\Notification\Domain\ProviderEntry;
use WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\SmsPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\WhatsAppPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\ProviderSendResult;
use WorkEddy\Modules\Notification\Infrastructure\Clients\SmsGatewayClientInterface;
use WorkEddy\Modules\Notification\Infrastructure\Clients\WhatsAppGatewayClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TwilioMessagingClient implements SmsGatewayClientInterface, WhatsAppGatewayClientInterface
{
    private const API_BASE_URL = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    public function sendSms(SmsPayload $payload, ProviderEntry $provider): ProviderSendResult
    {
        $from = $payload->from ?: $provider->getConfigValue('sms_from', '');

        return $this->sendMessage($payload->to, $from, $payload->body, $provider);
    }

    public function sendWhatsApp(WhatsAppPayload $payload, ProviderEntry $provider): ProviderSendResult
    {
        $from = $payload->from ?: $provider->getConfigValue('whatsapp_from', '');

        // Twilio requires 'whatsapp:' prefix for WhatsApp messages
        $to = str_starts_with($payload->to, 'whatsapp:') ? $payload->to : 'whatsapp:' . $payload->to;
        $from = str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:' . $from;

        return $this->sendMessage($to, $from, $payload->body, $provider);
    }

    private function sendMessage(string $to, string $from, string $body, ProviderEntry $provider): ProviderSendResult
    {
        $accountSid = $provider->getConfigValue('account_sid', '');
        $authToken = $provider->getConfigValue('auth_token', '');
        $statusCallback = $provider->getConfigValue('status_callback_url', '');

        // We can optionally store timeout in config, or hardcode/pass from elsewhere. 
        // For Twilio stateless adapter, default reasonable values:
        $timeout = (int) $provider->getConfigValue('timeout_seconds', 10);

        if (empty($accountSid) || empty($authToken)) {
            return new ProviderSendResult(
                success: false,
                provider: 'twilio',
                errorMessage: 'Twilio credentials are not configured.',
                failureType: FailureType::CONFIGURATION_ERROR
            );
        }

        if (empty($from)) {
            return new ProviderSendResult(
                success: false,
                provider: 'twilio',
                errorMessage: 'Sender number is not configured.',
                failureType: FailureType::CONFIGURATION_ERROR
            );
        }

        $url = sprintf(self::API_BASE_URL, $accountSid);

        $formData = [
            'To' => $to,
            'From' => $from,
            'Body' => $body,
        ];

        if (!empty($statusCallback)) {
            $formData['StatusCallback'] = $statusCallback;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$accountSid, $authToken],
                'body' => $formData,
                'timeout' => $timeout,
                'max_duration' => $timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false); // don't throw on error status

            if ($statusCode >= 200 && $statusCode < 300) {
                return new ProviderSendResult(
                    success: true,
                    provider: 'twilio',
                    providerMessageId: $data['sid'] ?? null,
                    status: $data['status'] ?? null,
                    rawResponse: $data,
                    sentAt: $this->clock->now()
                );
            }

            return $this->mapErrorResponse($statusCode, $data);
        } catch (TransportExceptionInterface $e) {
            return new ProviderSendResult(
                success: false,
                provider: 'twilio',
                errorMessage: 'Network error: ' . $e->getMessage(),
                failureType: FailureType::TEMPORARY_FAILURE
            );
        } catch (\Throwable $e) {
            return new ProviderSendResult(
                success: false,
                provider: 'twilio',
                errorMessage: 'Unexpected error: ' . $e->getMessage(),
                failureType: FailureType::TEMPORARY_FAILURE
            );
        }
    }

    private function mapErrorResponse(int $statusCode, array $data): ProviderSendResult
    {
        $errorCode = (string) ($data['code'] ?? $statusCode);
        $errorMessage = $data['message'] ?? 'Unknown Twilio Error';

        $failureType = FailureType::PERMANENT_FAILURE;

        if ($statusCode === 429) {
            $failureType = FailureType::RATE_LIMITED;
        } elseif ($statusCode === 401 || $statusCode === 403) {
            $failureType = FailureType::CONFIGURATION_ERROR;
        } elseif ($statusCode === 400) {
            // Check for invalid number errors (e.g., Twilio 21211, 21614)
            if (in_array((int)$errorCode, [21211, 21614], true)) {
                $failureType = FailureType::RECIPIENT_INVALID;
            } else {
                $failureType = FailureType::PROVIDER_REJECTED;
            }
        } elseif ($statusCode >= 500) {
            $failureType = FailureType::TEMPORARY_FAILURE;
        }

        return new ProviderSendResult(
            success: false,
            provider: 'twilio',
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            failureType: $failureType,
            rawResponse: $data
        );
    }
}
