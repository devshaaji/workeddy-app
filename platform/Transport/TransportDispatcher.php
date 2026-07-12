<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Lock\LockManagerContract;
use WorkEddy\Platform\Logging\ILoggerFactory;
use Psr\Log\LoggerInterface;

final class TransportDispatcher
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly TransportStoreInterface $store,
        private readonly TransportDriverRegistry $drivers,
        private readonly IClock $clock,
        private readonly ConfigLoader $config,
        private readonly LockManagerContract $locks,
        ILoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->channel('transport');
    }

    public function runOnce(int $limit = 100): TransportDispatchReport
    {
        $messages = $this->store->claimDue($limit, $this->clock->now());
        $delivered = 0;
        $failed = 0;
        $retried = 0;
        $fallbacks = 0;
        $errors = [];

        foreach ($messages as $message) {
            try {
                $outcome = $this->locks->synchronized(
                    'transport:' . $message->uuid,
                    function () use ($message, &$fallbacks): string {
                        return $this->dispatch($message, $fallbacks);
                    },
                    30,
                );
                if ($outcome === TransportMessage::STATUS_DELIVERED) {
                    $delivered++;
                } elseif ($outcome === TransportMessage::STATUS_RETRYING) {
                    $retried++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = $message->uuid . ': ' . $exception->getMessage();
                $this->logger->error('Transport dispatch failed unexpectedly.', [
                    'message_uuid' => $message->uuid,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return new TransportDispatchReport(count($messages), $delivered, $failed, $retried, $fallbacks, $errors);
    }

    private function dispatch(TransportMessage $message, int &$fallbacks): string
    {
        $destinations = $this->destinationChain($message->destination);
        if ($destinations === []) {
            $now = $this->clock->now();
            $missing = new TransportDestination($message->destination, 'null', null, null, 'none', null, false, 1, [], [], $now, $now);
            $this->store->markFailed($message, $missing, TransportResult::failure('Transport destination not configured.', false), $now);

            return TransportMessage::STATUS_FAILED;
        }

        $lastResult = null;
        $lastDestination = null;
        foreach ($destinations as $index => $destination) {
            $driver = $this->drivers->resolve($destination->driver);
            if (!$destination->enabled || !$driver->isAvailable($destination)) {
                $lastResult = TransportResult::failure('Transport destination unavailable.', false);
                $lastDestination = $destination;
                $this->store->recordAttempt($message, $destination, $lastResult, $this->clock->now());
                $this->logger->warning('Transport destination unavailable.', [
                    'message_uuid' => $message->uuid,
                    'destination' => $destination->name,
                    'driver' => $destination->driver,
                ]);
                if ($index < count($destinations) - 1) {
                    $fallbacks++;
                    $this->logger->notice('Transport fallback destination selected.', [
                        'message_uuid' => $message->uuid,
                        'from' => $destination->name,
                        'to' => $destinations[$index + 1]->name,
                    ]);
                    continue;
                }
                break;
            }

            $this->logger->info('Transport delivery attempt started.', [
                'message_uuid' => $message->uuid,
                'destination' => $destination->name,
                'driver' => $destination->driver,
            ]);
            $result = $driver->send($message, $destination);
            $lastResult = $result;
            $lastDestination = $destination;
            if ($result->success) {
                $this->store->markDelivered($message, $destination, $result, $this->clock->now());
                $this->logger->info('Transport delivery succeeded.', [
                    'message_uuid' => $message->uuid,
                    'destination' => $destination->name,
                    'status_code' => $result->statusCode,
                ]);

                return TransportMessage::STATUS_DELIVERED;
            }

            $this->logger->warning('Transport delivery failed.', [
                'message_uuid' => $message->uuid,
                'destination' => $destination->name,
                'status_code' => $result->statusCode,
                'retryable' => $result->retryable,
                'error' => $result->errorMessage,
            ]);
            if (!$result->retryable && $index < count($destinations) - 1) {
                $this->store->recordAttempt($message, $destination, $result, $this->clock->now());
                $fallbacks++;
                $this->logger->notice('Transport fallback destination selected.', [
                    'message_uuid' => $message->uuid,
                    'from' => $destination->name,
                    'to' => $destinations[$index + 1]->name,
                ]);
                continue;
            }

            break;
        }

        $now = $this->clock->now();
        $lastResult ??= TransportResult::failure('Transport delivery failed.', false);
        $lastDestination ??= $destinations[0];
        if ($lastResult->retryable && $this->canRetry($message)) {
            $nextAttemptAt = $now->add(new \DateInterval('PT' . $this->retryDelaySeconds($message->attemptCount) . 'S'));
            $this->store->scheduleRetry($message, $lastDestination, $lastResult, $nextAttemptAt, $now);
            $this->logger->notice('Transport retry scheduled.', [
                'message_uuid' => $message->uuid,
                'next_attempt_at' => $nextAttemptAt->format(DATE_ATOM),
            ]);

            return TransportMessage::STATUS_RETRYING;
        }

        $this->store->markFailed($message, $lastDestination, $lastResult, $now);

        return TransportMessage::STATUS_FAILED;
    }

    /**
     * @return list<TransportDestination>
     */
    private function destinationChain(string $destination): array
    {
        $primary = $this->store->findDestination($destination);
        if ($primary === null) {
            return [];
        }

        $chain = [$primary];
        foreach ($primary->fallbackDestinations as $fallback) {
            $resolved = $this->store->findDestination($fallback);
            if ($resolved !== null) {
                $chain[] = $resolved;
            }
        }

        return $chain;
    }

    private function canRetry(TransportMessage $message): bool
    {
        return $message->maxAttempts === 0 || ($message->attemptCount + 1) < $message->maxAttempts;
    }

    private function retryDelaySeconds(int $attemptCount): int
    {
        $backoff = $this->config->get('transport.retry.backoff_seconds', [30, 120, 300, 900]);
        if (!is_array($backoff) || $backoff === []) {
            return 30;
        }

        $index = min(max(0, $attemptCount), count($backoff) - 1);

        return max(1, (int) $backoff[$index]);
    }
}
