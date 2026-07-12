<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Drivers;

final class HttpPollingTransportDriver extends HttpTransportDriver
{
    public function supports(string $driver): bool
    {
        return $driver === 'polling' || $driver === 'http_polling';
    }
}
