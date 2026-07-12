<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Lock\LockManagerContract;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Transport\Shared\RetryPolicy;
use WorkEddy\Platform\Transport\Shared\TransportAckPublisherInterface;
use Psr\Log\LoggerInterface;

final class TransportInboxProcessor
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly TransportInboxRepository $inbox,
        private readonly TransportMessageHandlerRegistry $handlers,
        private readonly TransportAckPublisherInterface $acks,
        private readonly IClock $clock,
        private readonly ConfigLoader $config,
        private readonly LockManagerContract $locks,
        ILoggerFactory $loggerFactory,
    ) {
        $this->logger = $loggerFactory->channel('transport');
    }

    public function processPending(int $limit = 100): InboxProcessingReport
    {
        $messages = $this->inbox->claimPending($limit, $this->clock->now());
        $processed = 0;
        $failed = 0;
        $retried = 0;
        $rejected = 0;
        $acksPublished = 0;
        $errors = [];

        foreach ($messages as $message) {
            try {
                $outcome = $this->locks->synchronized(
                    'transport-inbox:' . $message->uuid,
                    function () use ($message, &$acksPublished): string {
                        return $this->processOne($message, $acksPublished);
                    },
                    30,
                );

                if ($outcome === TransportInboxMessage::STATUS_PROCESSED) {
                    $processed++;
                } elseif ($outcome === TransportInboxMessage::STATUS_RETRYING) {
                    $retried++;
                } elseif ($outcome === TransportInboxMessage::STATUS_REJECTED) {
                    $rejected++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = $message->uuid . ': ' . $exception->getMessage();
                $this->logger->error('Inbound transport processing failed unexpectedly.', [
                    'inbox_uuid' => $message->uuid,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return new InboxProcessingReport(count($messages), $processed, $failed, $retried, $rejected, $acksPublished, $errors);
    }

    private function processOne(TransportInboxMessage $message, int &$acksPublished): string
    {
        $startedAt = $this->clock->now();
        $handler = $this->handlers->resolve($message->topic);
        if ($handler === null) {
            $this->inbox->markRejected($message, 'No inbound transport handler registered for topic.', 'NO_HANDLER', $startedAt);
            $this->inbox->recordAttempt($message, $message->attemptCount + 1, TransportInboxMessage::STATUS_REJECTED, null, $startedAt, $this->clock->now(), 'No inbound transport handler registered for topic.', 'NO_HANDLER', false);

            return TransportInboxMessage::STATUS_REJECTED;
        }

        $result = $handler->handle($message);
        $finishedAt = $this->clock->now();
        $handlerClass = $handler::class;
        if ($result->success) {
            $this->inbox->markProcessed($message, $result, $finishedAt);
            $this->inbox->recordAttempt($message, $message->attemptCount + 1, TransportInboxMessage::STATUS_PROCESSED, $handlerClass, $startedAt, $finishedAt, null, null, false);
            if ($message->processedAckRequired) {
                $this->acks->publishProcessedAck($message, $result);
                $this->inbox->markProcessedAckSent($message, $this->clock->now());
                $acksPublished++;
            }

            return TransportInboxMessage::STATUS_PROCESSED;
        }

        $this->inbox->recordAttempt($message, $message->attemptCount + 1, $result->retryable ? TransportInboxMessage::STATUS_RETRYING : TransportInboxMessage::STATUS_FAILED, $handlerClass, $startedAt, $finishedAt, $result->errorMessage, $result->errorCode, $result->retryable);
        if ($result->retryable && $this->retryPolicy()->canRetry($message->attemptCount, $message->maxAttempts)) {
            $this->inbox->scheduleRetry($message, $result, $finishedAt->add(new \DateInterval('PT' . $this->retryPolicy()->delaySeconds($message->attemptCount) . 'S')), $finishedAt);

            return TransportInboxMessage::STATUS_RETRYING;
        }

        $this->inbox->markFailed($message, $result, $finishedAt);

        return TransportInboxMessage::STATUS_FAILED;
    }

    private function retryPolicy(): RetryPolicy
    {
        $backoff = $this->config->get('transport_inbound.retry.backoff_seconds', [30, 120, 300, 900]);

        return new RetryPolicy(is_array($backoff) ? array_values(array_map('intval', $backoff)) : [30, 120, 300, 900]);
    }
}
