<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients;

use WorkEddy\Modules\Notification\Domain\ProviderEntry;
use WorkEddy\Modules\Notification\Domain\ProviderTypeRegistry;
use WorkEddy\Modules\Notification\Settings\NotificationSettings;
use WorkEddy\Platform\Settings\SettingsService;
use Psr\Container\ContainerInterface;

final class ProviderRouter
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ContainerInterface $container
    ) {}

    public function resolve(string $channel): ResolvedProvider
    {
        $activeMap = $this->settings->get('notification.' . NotificationSettings::ACTIVE_PROVIDER_PER_CHANNEL, []);
        $providerKey = $activeMap[$channel] ?? null;

        if (!$providerKey) {
            throw new \RuntimeException(sprintf('No active provider configured for channel: %s', $channel));
        }

        $providerList = $this->settings->get('notification.' . NotificationSettings::PROVIDER_LIST, []);

        $providerData = null;
        foreach ($providerList as $p) {
            if (($p['key'] ?? '') === $providerKey) {
                $providerData = $p;
                break;
            }
        }

        if (!$providerData) {
            throw new \RuntimeException(sprintf('Provider key "%s" not found in provider list', $providerKey));
        }

        $entry = ProviderEntry::fromArray($providerData);

        if (!$entry->enabled) {
            throw new \RuntimeException(sprintf('Provider "%s" is disabled', $providerKey));
        }

        if (!in_array($channel, $entry->channels, true)) {
            throw new \RuntimeException(sprintf('Provider "%s" does not support channel "%s"', $providerKey, $channel));
        }

        $error = ProviderTypeRegistry::validate($entry->providerType, $entry->config);
        if ($error) {
            throw new \RuntimeException(sprintf('Invalid configuration for provider "%s": %s', $providerKey, $error));
        }

        $clientMap = [
            'twilio' => \WorkEddy\Modules\Notification\Infrastructure\Clients\Twilio\TwilioMessagingClient::class,
            'smtp' => \WorkEddy\Modules\Notification\Infrastructure\Clients\Smtp\SmtpEmailGatewayClient::class,
        ];

        if (!isset($clientMap[$entry->providerType])) {
            throw new \RuntimeException(sprintf('Unsupported provider type: %s', $entry->providerType));
        }

        $client = $this->container->get($clientMap[$entry->providerType]);

        return new ResolvedProvider($client, $entry);
    }
}
