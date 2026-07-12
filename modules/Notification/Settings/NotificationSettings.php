<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class NotificationSettings extends ModuleSettings
{
    public const DEFAULT_FROM_EMAIL = 'default_from_email';
    public const DEFAULT_FROM_NAME  = 'default_from_name';
    public const QUEUE_ENABLED      = 'queue_enabled';
    public const FALLBACK_ENABLED   = 'fallback_enabled';
    public const RETRY_MAX_ATTEMPTS = 'retry_max_attempts';
    public const RETRY_DELAY_SECONDS = 'retry_delay_seconds';
    public const HTTP_TIMEOUT_SECONDS = 'http_timeout_seconds';
    public const HTTP_CONNECT_TIMEOUT_SECONDS = 'http_connect_timeout_seconds';
    public const PROVIDER_LIST      = 'provider_list';
    public const ACTIVE_PROVIDER_PER_CHANNEL = 'active_provider_per_channel';

    protected function moduleName(): string
    {
        return 'notification';
    }

    public function defaultFromEmail(): string
    {
        return $this->getString(self::DEFAULT_FROM_EMAIL);
    }

    public function defaultFromName(): string
    {
        return $this->getString(self::DEFAULT_FROM_NAME);
    }

    public function queueEnabled(): bool
    {
        return $this->getBool(self::QUEUE_ENABLED);
    }

    public function fallbackEnabled(): bool
    {
        return $this->getBool(self::FALLBACK_ENABLED);
    }

    public function retryMaxAttempts(): int
    {
        return $this->getInt(self::RETRY_MAX_ATTEMPTS);
    }

    public function retryDelaySeconds(): int
    {
        return $this->getInt(self::RETRY_DELAY_SECONDS);
    }

    public function httpTimeoutSeconds(): int
    {
        return $this->getInt(self::HTTP_TIMEOUT_SECONDS);
    }

    public function httpConnectTimeoutSeconds(): int
    {
        return $this->getInt(self::HTTP_CONNECT_TIMEOUT_SECONDS);
    }

    public function providerList(): array
    {
        return $this->getJson(self::PROVIDER_LIST);
    }

    public function activeProviderForChannel(string $channel): ?string
    {
        $map = $this->getJson(self::ACTIVE_PROVIDER_PER_CHANNEL);
        return $map[$channel] ?? null;
    }
}
