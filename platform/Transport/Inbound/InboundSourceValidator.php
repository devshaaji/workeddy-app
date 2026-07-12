<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Transport\Shared\PayloadSerializer;
use WorkEddy\Platform\Transport\Shared\RuntimeMessageSigner;

final class InboundSourceValidator
{
    public function __construct(
        private readonly TransportInboundSourceRepository $sources,
        private readonly IClock $clock,
        private readonly RuntimeMessageSigner $signer,
        private readonly PayloadSerializer $serializer,
    ) {}

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     */
    public function validate(string $sourceName, string $topic, array $headers, array $payload = []): InboundSourceValidationResult
    {
        $source = $this->sources->findByName($sourceName);
        if ($source === null) {
            return new InboundSourceValidationResult(false, null, 'Unknown inbound transport source.', 'UNKNOWN_SOURCE');
        }

        if (!$source->enabled) {
            return new InboundSourceValidationResult(false, $source, 'Inbound transport source is disabled.', 'SOURCE_DISABLED');
        }

        if (!$this->topicAllowed($source, $topic)) {
            return new InboundSourceValidationResult(false, $source, 'Inbound transport topic is not allowed for this source.', 'TOPIC_NOT_ALLOWED');
        }

        if (!$this->timestampAllowed($source, $headers)) {
            return new InboundSourceValidationResult(false, $source, 'Inbound transport timestamp is outside the allowed clock skew.', 'CLOCK_SKEW');
        }

        if ($source->requireSignature) {
            $signature = $this->header($headers, $source->signatureHeader);
            if ($signature === null || $signature === '') {
                return new InboundSourceValidationResult(false, $source, 'Inbound transport signature is required.', 'SIGNATURE_REQUIRED');
            }
            if ($source->authType === 'hmac') {
                $timestamp = $this->header($headers, $source->timestampHeader);
                $secret = $this->hmacSecret($source);
                if ($timestamp === null || $timestamp === '' || $secret === null) {
                    return new InboundSourceValidationResult(false, $source, 'Inbound transport HMAC credentials are incomplete.', 'SIGNATURE_INVALID');
                }
                if (!$this->signer->verify($secret, $timestamp, $this->serializer->encode($payload), $signature)) {
                    return new InboundSourceValidationResult(false, $source, 'Inbound transport signature is invalid.', 'SIGNATURE_INVALID');
                }
            }
        }

        return new InboundSourceValidationResult(true, $source);
    }

    private function topicAllowed(TransportInboundSource $source, string $topic): bool
    {
        if ($source->allowedTopics === [] || in_array('*', $source->allowedTopics, true)) {
            return true;
        }

        foreach ($source->allowedTopics as $allowed) {
            if ($allowed === $topic) {
                return true;
            }
            if (str_ends_with($allowed, '.*') && str_starts_with($topic, substr($allowed, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function timestampAllowed(TransportInboundSource $source, array $headers): bool
    {
        $value = $this->header($headers, $source->timestampHeader);
        if ($value === null || $value === '') {
            return true;
        }

        try {
            $timestamp = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return false;
        }

        return abs($this->clock->now()->getTimestamp() - $timestamp->getTimestamp()) <= $source->maxClockSkewSeconds;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        return $this->header($headers, $name) !== null;
    }

    private function hmacSecret(TransportInboundSource $source): ?string
    {
        if ($source->secretHash === null || $source->secretHash === '') {
            return null;
        }

        if (str_starts_with($source->secretHash, 'plain:')) {
            return substr($source->secretHash, 6);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return (string) $value;
            }
        }

        return null;
    }
}
