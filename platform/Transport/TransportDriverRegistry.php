<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

final class TransportDriverRegistry
{
    /** @var list<TransportDriverInterface> */
    private array $drivers = [];

    public function register(TransportDriverInterface $driver): void
    {
        $this->drivers[] = $driver;
    }

    public function resolve(string $driver): TransportDriverInterface
    {
        foreach ($this->drivers as $candidate) {
            if ($candidate->supports($driver)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No transport driver registered for [' . $driver . '].');
    }
}
