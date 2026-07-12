<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Notification\Application\Job\SendNotificationJob;
use WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationProviderInterface;
use WorkEddy\Modules\Notification\Contracts\TemplateRendererInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationDeliveryLog;
use WorkEddy\Modules\Notification\Domain\NotificationMessage;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Settings\SettingsService;
use Psr\Container\ContainerInterface;

final class SendNotificationUseCase
{
    public function __construct(
        private readonly ChannelResolverInterface $channelResolver,
        private readonly TemplateRendererInterface $templateRenderer,
        private readonly NotificationLogRepositoryInterface $logRepository,
        private readonly UuidGeneratorContract $uuidGenerator,
        private readonly ContainerInterface $container,
        private readonly SettingsService $settings,
        private readonly IQueueService $queueService,
        private readonly IClock $clock,
        private readonly ResolveRecipientNotificationChannels $recipientChannelResolver,
    ) {}

    public function execute(NotificationRequest $request, int $attemptCount = 1, ?string $logUuid = null): void
    {
        $existingLog = $logUuid ? $this->logRepository->findByUuid($logUuid) : null;
        $uuid = $logUuid ?? $this->uuidGenerator->generate();
        $id = $existingLog ? $existingLog->id : null;

        $maxAttempts = (int) $this->settings->get('notification.retry_max_attempts', 3);
        $fallbackEnabled = (bool) $this->settings->get('notification.fallback_enabled', true);

        $channels = $this->recipientChannelResolver->resolve($request);

        foreach ($channels as $channel) {
            $provider = $this->getProvider($channel);
            if (!$provider) {
                continue;
            }

            try {
                $subject = $this->templateRenderer->getSubject($request->type, $channel, $request->data);
                $body = $this->templateRenderer->render($request->type, $channel, $request->data);

                $message = new NotificationMessage(
                    channel: $channel,
                    recipient: $request->recipient,
                    subject: $subject ?? 'Notification',
                    body: $body,
                    isHtml: $channel === NotificationChannel::EMAIL,
                    metadata: $request->metadata
                );

                $result = $provider->send($message);

                // For providers that don't return FailureType, assume TEMPORARY_FAILURE on general error
                $failureType = null;
                if (property_exists($result, 'failureType')) {
                    $failureType = $result->failureType;
                }

                if (!$result->success && $failureType === null) {
                    $failureType = FailureType::TEMPORARY_FAILURE;
                }

                $log = new NotificationDeliveryLog(
                    uuid: $uuid,
                    notificationType: $request->type->value,
                    recipientType: $request->recipient->recipientType,
                    recipientId: $request->recipient->recipientId,
                    channel: $channel,
                    provider: get_class($provider),
                    status: $result->success ? 'sent' : 'failed',
                    subject: $subject,
                    messagePreview: substr($body, 0, 500),
                    recipientName: $request->recipient->name,
                    recipientEmail: $request->recipient->email,
                    recipientPhone: $request->recipient->phone,
                    attemptCount: $attemptCount,
                    failureReason: $result->errorMessage,
                    failureType: $failureType,
                    providerMessageId: property_exists($result, 'providerMessageId') ? $result->providerMessageId : null,
                    metadataJson: $request->metadata,
                    queuedAt: $existingLog ? $existingLog->queuedAt : null,
                    sentAt: $result->success ? $this->clock->now() : null,
                    failedAt: !$result->success ? $this->clock->now() : null,
                    id: $id,
                    createdAt: $existingLog ? $existingLog->createdAt : null
                );

                $this->logRepository->save($log);

                if ($result->success) {
                    return; // Successfully sent
                }

                // Handle Failure Policies
                if ($this->shouldRetry($failureType, $attemptCount, $maxAttempts)) {
                    // Dispatch to queue again to retry (prevents worker blocking)
                    $this->queueService->dispatch(
                        SendNotificationJob::JOB_TYPE,
                        (new SendNotificationJob($request, $attemptCount + 1, $uuid))->toPayload(),
                    );
                    return; // Stop current execution, wait for retry
                }

                if (!$this->shouldFallback($failureType, $fallbackEnabled)) {
                    return; // Stop trying other channels
                }

                // If fallback is enabled and we exhausted retries or got permanent error, 
                // we continue the loop to try the next channel.

            } catch (\Throwable $e) {
                $log = new NotificationDeliveryLog(
                    uuid: $uuid,
                    notificationType: $request->type->value,
                    recipientType: $request->recipient->recipientType,
                    recipientId: $request->recipient->recipientId,
                    channel: $channel,
                    provider: get_class($provider) ?? 'Unknown',
                    status: 'failed',
                    failureReason: $e->getMessage(),
                    failureType: FailureType::TEMPORARY_FAILURE,
                    queuedAt: $existingLog ? $existingLog->queuedAt : null,
                    failedAt: $this->clock->now(),
                    id: $id,
                    createdAt: $existingLog ? $existingLog->createdAt : null
                );
                $this->logRepository->save($log);

                if ($attemptCount < $maxAttempts) {
                    $this->queueService->dispatch(
                        SendNotificationJob::JOB_TYPE,
                        (new SendNotificationJob($request, $attemptCount + 1, $uuid))->toPayload(),
                    );
                    return;
                }

                if (!$fallbackEnabled) {
                    return;
                }
            }
        }
    }

    private function shouldRetry(?FailureType $failureType, int $currentAttempt, int $maxAttempts): bool
    {
        if ($currentAttempt >= $maxAttempts) {
            return false;
        }

        if ($failureType === FailureType::TEMPORARY_FAILURE || $failureType === FailureType::RATE_LIMITED) {
            return true;
        }

        return false;
    }

    private function shouldFallback(?FailureType $failureType, bool $fallbackEnabled): bool
    {
        if (!$fallbackEnabled) {
            return false;
        }

        if ($failureType === FailureType::CONFIGURATION_ERROR) {
            // Usually if the provider is misconfigured, fallback to another channel is good.
            return true;
        }

        if ($failureType === FailureType::RECIPIENT_INVALID) {
            // E.g. Bad phone number -> try Email
            return true;
        }

        return true;
    }

    private function getProvider(NotificationChannel $channel): ?NotificationProviderInterface
    {
        $map = [
            NotificationChannel::IN_APP->value => \WorkEddy\Modules\Notification\Infrastructure\Providers\InAppNotificationProvider::class,
            NotificationChannel::EMAIL->value => \WorkEddy\Modules\Notification\Infrastructure\Providers\EmailNotificationProvider::class,
            NotificationChannel::SMS->value => \WorkEddy\Modules\Notification\Infrastructure\Providers\SmsNotificationProvider::class,
            NotificationChannel::WHATSAPP->value => \WorkEddy\Modules\Notification\Infrastructure\Providers\WhatsAppNotificationProvider::class,
        ];

        if (!isset($map[$channel->value])) {
            return null;
        }

        return $this->container->get($map[$channel->value]);
    }
}
