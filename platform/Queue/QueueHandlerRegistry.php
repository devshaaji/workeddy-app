<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Queue;

final class QueueHandlerRegistry
{
    /** @var array<string, QueueJobHandlerInterface> */
    private array $handlers = [];

    /**
     * @param array<string, QueueJobHandlerInterface> $handlers
     */
    public function __construct(array $handlers = [])
    {
        foreach ($handlers as $jobType => $handler) {
            $this->register((string) $jobType, $handler);
        }
    }

    public function register(string $jobType, QueueJobHandlerInterface $handler): void
    {
        $this->handlers[$jobType] = $handler;
    }

    public function get(string $jobType): ?QueueJobHandlerInterface
    {
        return $this->handlers[$jobType] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->handlers === [];
    }

    /**
     * @return list<string>
     */
    public function jobTypes(): array
    {
        return array_keys($this->handlers);
    }
}
