<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

interface TransportDriverInterface
{
    public function send(TransportMessage $message, TransportDestination $destination): TransportResult;

    public function isAvailable(TransportDestination $destination): bool;

    public function supports(string $driver): bool;
}
