<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Drivers;

use WorkEddy\Platform\Transport\TransportDestination;
use WorkEddy\Platform\Transport\TransportDriverInterface;
use WorkEddy\Platform\Transport\TransportJson;
use WorkEddy\Platform\Transport\TransportMessage;
use WorkEddy\Platform\Transport\TransportResult;

class HttpTransportDriver implements TransportDriverInterface
{
    public function send(TransportMessage $message, TransportDestination $destination): TransportResult
    {
        $url = $this->url($destination);
        if ($url === null) {
            return TransportResult::failure('HTTP transport destination has no endpoint.', false);
        }

        $headers = $this->headers($message, $destination);
        $body = TransportJson::encode($this->buildRequestPayload(
            $message,
            $destination,
            'cloud.primary',
        ));

        $responseHeaders = [];
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => max(1, $destination->timeoutSeconds),
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static function (): bool {
            return true;
        });
        try {
            $responseBody = file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header ?? [];
        } finally {
            restore_error_handler();
        }

        $statusCode = $this->statusCode($responseHeaders);
        if ($responseBody === false || $statusCode === null) {
            return TransportResult::failure('HTTP transport request failed.', true, $statusCode);
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return TransportResult::success($statusCode, $responseBody, $this->remoteMessageId($responseHeaders), new \DateTimeImmutable());
        }

        return TransportResult::failure(
            'HTTP transport returned status ' . $statusCode . '.',
            $statusCode === 408 || $statusCode === 429 || $statusCode >= 500,
            $statusCode,
            $responseBody,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestPayload(TransportMessage $message, TransportDestination $destination, string $runtimeId): array
    {
        unset($destination);

        return [
            'source' => $runtimeId,
            'topic' => $message->topic,
            'payload' => $message->payload,
            'headers' => $this->sanitize($message->headers),
            'idempotency_key' => $message->idempotencyKey,
            'correlation_id' => $message->correlationId,
            'remote_message_id' => $message->uuid,
            'received_ack_required' => true,
            'processed_ack_required' => false,
            'created_at' => $message->createdAt->format(DATE_ATOM),
        ];
    }

    public function isAvailable(TransportDestination $destination): bool
    {
        return $destination->enabled && $this->url($destination) !== null;
    }

    public function supports(string $driver): bool
    {
        return $driver === 'http' || $driver === 'http_push';
    }

    protected function url(TransportDestination $destination): ?string
    {
        $endpoint = trim((string) ($destination->endpoint ?? ''));
        $baseUrl = rtrim(trim((string) ($destination->baseUrl ?? '')), '/');
        if ($endpoint === '' && $baseUrl === '') {
            return null;
        }

        if ($endpoint !== '' && preg_match('#^https?://#i', $endpoint) === 1) {
            return $endpoint;
        }

        if ($baseUrl === '') {
            return $endpoint === '' ? null : $endpoint;
        }

        return $endpoint === '' ? $baseUrl : $baseUrl . '/' . ltrim($endpoint, '/');
    }

    /**
     * @return list<string>
     */
    protected function headers(TransportMessage $message, TransportDestination $destination): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Transport-Topic: ' . $message->topic,
            'X-Transport-Message-Id: ' . $message->uuid,
        ];
        if ($message->idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $message->idempotencyKey;
        }
        if ($message->correlationId !== null) {
            $headers[] = 'X-Correlation-Id: ' . $message->correlationId;
        }

        $secret = trim((string) $destination->credentialsSecret);
        if ($secret === '') {
            return $headers;
        }

        if ($destination->authType === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $secret;
        } elseif ($destination->authType === 'api_key') {
            $headers[] = 'X-API-Key: ' . $secret;
        } elseif ($destination->authType === 'basic') {
            $credentials = json_decode($secret, true);
            if (is_array($credentials)) {
                $headers[] = 'Authorization: Basic ' . base64_encode((string) ($credentials['username'] ?? '') . ':' . (string) ($credentials['password'] ?? ''));
            }
        }

        return $headers;
    }

    /**
     * @param list<string> $headers
     */
    private function statusCode(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @param list<string> $headers
     */
    private function remoteMessageId(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'X-Message-Id:') === 0 || stripos($header, 'X-Request-Id:') === 0) {
                return trim(substr($header, strpos($header, ':') + 1));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    private function sanitize(array $headers): array
    {
        $sanitized = [];
        foreach ($headers as $key => $value) {
            $name = (string) $key;
            $sanitized[$name] = preg_match('/authorization|api-key|secret|token|password/i', $name) === 1 ? '[redacted]' : $value;
        }

        return $sanitized;
    }
}
