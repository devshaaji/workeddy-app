<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

use WorkEddy\Platform\Cache\ICacheService;
use WorkEddy\Platform\Config\ConfigLoader;

final class TransportCapabilityService
{
    public function __construct(
        private readonly ConfigLoader $config,
        private readonly ICacheService $cache,
    ) {}

    public function localCapabilities(): TransportCapability
    {
        return new TransportCapability(
            (string) $this->config->get('transport.capabilities.runtime_id', $this->config->get('app.runtime_id', 'edge-001')),
            (string) $this->config->get('transport.capabilities.runtime_type', 'edge'),
            $this->enabledOutboundModes(),
            $this->enabledInboundModes(),
            (string) $this->config->get('transport.capabilities.recommended_inbound_mode', 'polling'),
            $this->strings($this->config->get('transport.capabilities.fallback_modes', ['polling'])),
            $this->endpoints(),
        );
    }

    public function fetchRemoteCapabilities(string $baseUrl, bool $forceRefresh = false): ?TransportCapability
    {
        $baseUrl = rtrim($baseUrl, '/');
        $cacheKey = 'transport.capabilities.' . sha1($baseUrl);
        if (!$forceRefresh) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return TransportCapability::fromArray($cached);
            }
        }

        $url = $baseUrl . '/api/v1/transport/capabilities';
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true]]);
        set_error_handler(static fn(): bool => true);
        try {
            $body = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }
        if ($body === false || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        $capabilities = TransportCapability::fromArray($decoded);
        $this->cache->set($cacheKey, $capabilities->toArray(), (int) $this->config->get('transport.capabilities.remote_cache_ttl_seconds', 300));

        return $capabilities;
    }

    public function selectMode(TransportCapability $remote, ?string $preferredMode = null): TransportModeSelection
    {
        $localOutbound = $this->enabledOutboundModes();
        $remoteInbound = $remote->supportedInboundModes;
        $candidates = [];
        if ($preferredMode !== null && $preferredMode !== '') {
            $candidates[] = $preferredMode;
        }
        if ($remote->recommendedInboundMode !== '') {
            $candidates[] = $remote->recommendedInboundMode;
        }
        $candidates = array_merge($candidates, $this->strings($this->config->get('transport.capabilities.preferred_modes', [])), $remote->fallbackModes, ['http_push', 'http', 'polling', 'sse', 'websocket']);

        foreach (array_values(array_unique($candidates)) as $mode) {
            if (!$this->modeEnabled($mode) || !$this->localSupportsRemoteInbound($mode, $localOutbound, $remoteInbound)) {
                continue;
            }

            return new TransportModeSelection(true, $mode, $this->endpointForMode($mode, $remote->endpoints));
        }

        return new TransportModeSelection(false, null, null, 'No compatible transport mode was found.');
    }

    /**
     * @return list<string>
     */
    private function enabledOutboundModes(): array
    {
        $modes = [];
        foreach ((array) $this->config->get('transport.drivers', []) as $mode => $settings) {
            if (is_array($settings) && (bool) ($settings['enabled'] ?? false)) {
                $modes[] = $mode === 'http' ? 'http' : (string) $mode;
            }
        }

        return $modes;
    }

    /**
     * @return list<string>
     */
    private function enabledInboundModes(): array
    {
        $modes = [];
        foreach ((array) $this->config->get('transport_inbound.receive_modes', []) as $mode => $settings) {
            if (is_array($settings) && (bool) ($settings['enabled'] ?? false)) {
                $modes[] = $mode === 'http_webhook' ? 'http_push' : (string) $mode;
            }
        }

        return $modes;
    }

    private function localSupportsRemoteInbound(string $mode, array $localOutbound, array $remoteInbound): bool
    {
        $localMode = $mode === 'http_push' ? 'http' : $mode;

        return in_array($localMode, $localOutbound, true) && in_array($mode, $remoteInbound, true);
    }

    private function modeEnabled(string $mode): bool
    {
        $key = match ($mode) {
            'http_push' => 'transport.drivers.http.enabled',
            default => 'transport.drivers.' . $mode . '.enabled',
        };

        return (bool) $this->config->get($key, false);
    }

    /**
     * @return array<string, string|null>
     */
    private function endpoints(): array
    {
        $configured = $this->config->get('transport.capabilities.endpoints', []);
        if (is_array($configured) && $configured !== []) {
            return array_map(static fn(mixed $value): ?string => $value === null ? null : (string) $value, $configured);
        }

        return [
            'http_inbound' => '/api/v1/transport/inbound',
            'batch_inbound' => '/api/v1/transport/inbound/batch',
            'polling' => '/api/v1/edge/commands',
            'polling_ack' => '/api/v1/edge/commands/ack',
            'sse' => '/api/v1/edge/stream',
            'websocket' => null,
        ];
    }

    /**
     * @param array<string, string|null> $endpoints
     */
    private function endpointForMode(string $mode, array $endpoints): ?string
    {
        return match ($mode) {
            'http_push', 'http' => $endpoints['http_inbound'] ?? null,
            'polling' => $endpoints['polling'] ?? null,
            'sse' => $endpoints['sse'] ?? null,
            'websocket' => $endpoints['websocket'] ?? null,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
