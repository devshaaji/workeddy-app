<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

final class TransportCapability
{
    /**
     * @param list<string> $supportedOutboundModes
     * @param list<string> $supportedInboundModes
     * @param list<string> $fallbackModes
     * @param array<string, string|null> $endpoints
     */
    public function __construct(
        public readonly string $runtimeId,
        public readonly string $runtimeType,
        public readonly array $supportedOutboundModes,
        public readonly array $supportedInboundModes,
        public readonly string $recommendedInboundMode,
        public readonly array $fallbackModes,
        public readonly array $endpoints,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'runtime_id' => $this->runtimeId,
            'runtime_type' => $this->runtimeType,
            'supported_outbound_modes' => $this->supportedOutboundModes,
            'supported_inbound_modes' => $this->supportedInboundModes,
            'recommended_inbound_mode' => $this->recommendedInboundMode,
            'fallback_modes' => $this->fallbackModes,
            'endpoints' => $this->endpoints,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['runtime_id'] ?? ''),
            (string) ($data['runtime_type'] ?? 'unknown'),
            self::strings($data['supported_outbound_modes'] ?? []),
            self::strings($data['supported_inbound_modes'] ?? []),
            (string) ($data['recommended_inbound_mode'] ?? ''),
            self::strings($data['fallback_modes'] ?? []),
            is_array($data['endpoints'] ?? null) ? array_map(static fn(mixed $value): ?string => $value === null ? null : (string) $value, $data['endpoints']) : [],
        );
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
