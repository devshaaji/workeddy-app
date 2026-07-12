<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

use WorkEddy\Platform\Module\ModuleRegistry;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use Psr\Container\ContainerInterface;

final class EventBus implements EventPublisherInterface
{
    private readonly \Psr\Log\LoggerInterface $logger;

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ContainerInterface $container,
        private readonly IQueueService $queue,
        ILoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->channel('EventBus');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $eventName, array $payload, string $idempotencyKey): void
    {
        $listenerClasses = $this->registry->listenerClassesFor($eventName);

        if (empty($listenerClasses)) {
            $this->logger->debug("No listeners registered for event.", ['event' => $eventName]);
            return;
        }

        foreach ($listenerClasses as $listenerClass) {
            if (is_subclass_of($listenerClass, IAsyncEventListener::class)) {
                $this->dispatchAsync($eventName, $payload, $idempotencyKey, $listenerClass);
            } else {
                $this->dispatchSync($eventName, $payload, $listenerClass);
            }
        }
    }

    private function dispatchAsync(string $eventName, array $payload, string $idempotencyKey, string $listenerClass): void
    {
        try {
            $this->queue->dispatch(AsyncEventJobHandler::JOB_TYPE, [
                'event' => $eventName,
                'listener' => $listenerClass,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->logger->info("Dispatched async event listener to queue.", [
                'event' => $eventName,
                'listener' => $listenerClass,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to enqueue async event listener.", [
                'event' => $eventName,
                'listener' => $listenerClass,
                'exception' => $e->getMessage()
            ]);
            // Depending on reliability requirements, we could rethrow or swallow.
            // Swallowing prevents breaking the main transaction.
        }
    }

    private function dispatchSync(string $eventName, array $payload, string $listenerClass): void
    {
        try {
            $listener = $this->container->get($listenerClass);

            if (!is_callable($listener)) {
                throw new \RuntimeException("Listener class '{$listenerClass}' is not callable.");
            }

            $listener($payload);

            $this->logger->info("Executed sync event listener.", [
                'event' => $eventName,
                'listener' => $listenerClass,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Synchronous event listener threw an exception.", [
                'event' => $eventName,
                'listener' => $listenerClass,
                'exception' => $e->getMessage()
            ]);
            // Rethrow so the caller knows the synchronous operation failed.
            throw $e;
        }
    }
}
