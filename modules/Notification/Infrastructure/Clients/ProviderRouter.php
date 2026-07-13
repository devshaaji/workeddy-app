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
        $providers = $this->resolveAllForChannel($channel);
        if ($providers === []) {
            throw new \RuntimeException(sprintf('No active provider configured for channel: %s', $channel));
        }

        return $providers[0];
    }

    /**
     * @return list<ResolvedProvider>
     */
    public function resolveAllForChannel(string $channel): array
    {
        $providerList = $this->settings->get('notification.' . NotificationSettings::PROVIDER_LIST, []);
        $activeMap = $this->settings->get('notification.' . NotificationSettings::ACTIVE_PROVIDER_PER_CHANNEL, []);
        $primaryKey = $activeMap[$channel] ?? null;

        $entries = [];
        foreach ($providerList as $providerData) {
            $entry = ProviderEntry::fromArray(is_array($providerData) ? $providerData : []);

            if (!$entry->enabled) {
                continue;
            }

            if (!in_array($channel, $entry->channels, true)) {
                continue;
            }

            $error = ProviderTypeRegistry::validate($entry->providerType, $entry->config);
            if ($error) {
                continue;
            }

            $entries[] = $entry;
        }

        usort($entries, static function (ProviderEntry $left, ProviderEntry $right) use ($primaryKey): int {
            if ($primaryKey !== null) {
                if ($left->key === $primaryKey && $right->key !== $primaryKey) {
                    return -1;
                }
                if ($right->key === $primaryKey && $left->key !== $primaryKey) {
                    return 1;
                }
            }

            return [$left->priority, $left->key] <=> [$right->priority, $right->key];
        });

        return array_values(array_map(function (ProviderEntry $entry): ResolvedProvider {
            return new ResolvedProvider($this->container->get($this->clientClassFor($entry)), $entry);
        }, $entries));
    }

    private function clientClassFor(ProviderEntry $entry): string
    {
        $clientMap = [
            'twilio' => \WorkEddy\Modules\Notification\Infrastructure\Clients\Twilio\TwilioMessagingClient::class,
            'smtp' => \WorkEddy\Modules\Notification\Infrastructure\Clients\Smtp\SmtpEmailGatewayClient::class,
        ];

        if (!isset($clientMap[$entry->providerType])) {
            throw new \RuntimeException(sprintf('Unsupported provider type: %s', $entry->providerType));
        }

        return $clientMap[$entry->providerType];
    }
}
