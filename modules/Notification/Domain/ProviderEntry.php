<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class ProviderEntry
{
    public function __construct(
        public readonly string $key,
        public readonly string $providerType,
        public readonly bool $enabled,
        public readonly array $channels,
        public readonly int $priority,
        public readonly array $config,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'] ?? '',
            providerType: $data['provider_type'] ?? '',
            enabled: (bool) ($data['enabled'] ?? false),
            channels: (array) ($data['channels'] ?? []),
            priority: (int) ($data['priority'] ?? 0),
            config: (array) ($data['config'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'provider_type' => $this->providerType,
            'enabled' => $this->enabled,
            'channels' => $this->channels,
            'priority' => $this->priority,
            'config' => $this->config,
        ];
    }

    public function getConfigValue(string $field, mixed $default = null): mixed
    {
        return $this->config[$field] ?? $default;
    }
}
