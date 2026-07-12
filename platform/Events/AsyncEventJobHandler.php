<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

use WorkEddy\Platform\Queue\QueueJob;
use WorkEddy\Platform\Queue\QueueJobHandlerInterface;
use WorkEddy\Platform\Logging\ILoggerFactory;
use Psr\Container\ContainerInterface;

final class AsyncEventJobHandler implements QueueJobHandlerInterface
{
    public const JOB_TYPE = 'async_event_dispatch';

    private readonly \Psr\Log\LoggerInterface $logger;

    public function __construct(
        private readonly ContainerInterface $container,
        ILoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->channel('EventBus');
    }

    public function handle(QueueJob $job): void
    {
        $listenerClass = $job->payload['listener'] ?? null;
        $eventName = $job->payload['event'] ?? 'unknown_event';
        $payload = $job->payload['payload'] ?? [];

        if (!is_string($listenerClass) || !class_exists($listenerClass)) {
            $this->logger->error("Async event job failed: Invalid listener class.", ['job' => $job->jobId, 'listener' => $listenerClass]);
            throw new \RuntimeException("Async event job failed: Invalid listener class '{$listenerClass}'.");
        }

        try {
            $listener = $this->container->get($listenerClass);

            if (!is_callable($listener)) {
                throw new \RuntimeException("Listener class '{$listenerClass}' is not callable (missing __invoke).");
            }

            // Execute the listener
            $listener($payload);

            $this->logger->info("Successfully executed async listener.", [
                'event' => $eventName,
                'listener' => $listenerClass
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Async listener threw exception.", [
                'event' => $eventName,
                'listener' => $listenerClass,
                'exception' => $e->getMessage()
            ]);
            throw $e; // Rethrow to let the QueueWorker handle the failure/retry mechanism
        }
    }
}
