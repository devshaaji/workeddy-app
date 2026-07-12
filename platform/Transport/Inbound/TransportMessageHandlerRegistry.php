<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class TransportMessageHandlerRegistry
{
    /** @var list<TransportMessageHandlerInterface> */
    private array $handlers = [];

    public function register(TransportMessageHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function resolve(string $topic): ?TransportMessageHandlerInterface
    {
        $matches = [];
        foreach ($this->handlers as $handler) {
            if ($handler->supports($topic)) {
                $matches[] = $handler;
            }
        }

        if (count($matches) > 1) {
            throw new \RuntimeException('Multiple inbound transport handlers support topic [' . $topic . '].');
        }

        return $matches[0] ?? null;
    }
}
