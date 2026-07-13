<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Notification\Application\Job\SendNotificationJob;
use WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface;
use WorkEddy\Modules\Notification\Contracts\TemplateRendererInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationChannelPolicy;
use WorkEddy\Modules\Notification\Domain\NotificationDeliveryAttempt;
use WorkEddy\Modules\Notification\Domain\NotificationDeliveryLog;
use WorkEddy\Modules\Notification\Domain\NotificationFallbackMode;
use WorkEddy\Modules\Notification\Domain\NotificationMessage;
use WorkEddy\Modules\Notification\Domain\NotificationProviderResult;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Infrastructure\Clients\EmailGatewayClientInterface;
use WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\EmailPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\SmsPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\WhatsAppPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\ProviderRouter;
use WorkEddy\Modules\Notification\Infrastructure\Clients\ResolvedProvider;
use WorkEddy\Modules\Notification\Infrastructure\Clients\SmsGatewayClientInterface;
use WorkEddy\Modules\Notification\Infrastructure\Clients\WhatsAppGatewayClientInterface;
use WorkEddy\Modules\Notification\Infrastructure\Providers\InAppNotificationProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Settings\SettingsService;

final class SendNotificationUseCase
{
    public function __construct(
        private readonly TemplateRendererInterface $templateRenderer,
        private readonly NotificationLogRepositoryInterface $logRepository,
        private readonly UuidGeneratorContract $uuidGenerator,
        private readonly SettingsService $settings,
        private readonly IQueueService $queueService,
        private readonly IClock $clock,
        private readonly ResolveRecipientNotificationChannels $recipientChannelResolver,
        private readonly ResolveNotificationChannels $channelPolicyResolver,
        private readonly ProviderRouter $providerRouter,
        private readonly InAppNotificationProvider $inAppProvider,
    ) {}

    public function execute(
        NotificationRequest $request,
        int $attemptCount = 1,
        ?string $logUuid = null,
        int $channelIndex = 0,
        int $providerIndex = 0
    ): void {
        $existingLog = $logUuid ? $this->logRepository->findByUuid($logUuid) : null;
        $uuid = $logUuid ?? $this->uuidGenerator->generate();
        $id = $existingLog?->id;

        $maxAttempts = (int) $this->settings->get('notification.retry_max_attempts', 3);
        $fallbackEnabled = (bool) $this->settings->get('notification.fallback_enabled', true);
        $policy = $this->channelPolicyResolver->policy($request->type);
        $channels = $this->recipientChannelResolver->resolve($request);

        if ($channels === []) {
            return;
        }

        for ($currentChannelIndex = $channelIndex; $currentChannelIndex < count($channels); $currentChannelIndex += 1) {
            $channel = $channels[$currentChannelIndex];
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

            if ($channel === NotificationChannel::IN_APP) {
                $result = $this->inAppProvider->send($message);
                $this->saveLog($uuid, $id, $existingLog, $request, $channel, 'in_app', $subject, $body, $attemptCount, $result);
                if ($result->success) {
                    return;
                }

                if (!$this->shouldFallbackToNextChannel($policy, $result->failureType, $fallbackEnabled)) {
                    return;
                }

                $providerIndex = 0;
                continue;
            }

            $providers = $this->providerRouter->resolveAllForChannel($channel->value);
            if ($providers === []) {
                if (!$this->shouldFallbackToNextChannel($policy, FailureType::CONFIGURATION_ERROR, $fallbackEnabled)) {
                    return;
                }
                $providerIndex = 0;
                continue;
            }

            for ($currentProviderIndex = ($currentChannelIndex === $channelIndex ? $providerIndex : 0); $currentProviderIndex < count($providers); $currentProviderIndex += 1) {
                $resolved = $providers[$currentProviderIndex];
                $result = $this->sendResolvedProvider($channel, $message, $resolved);

                $this->saveLog($uuid, $id, $existingLog, $request, $channel, $resolved->entry->key, $subject, $body, $attemptCount, $result);

                if ($result->success) {
                    return;
                }

                if ($this->shouldRetry($result->failureType, $attemptCount, $maxAttempts)) {
                    $this->queueService->dispatch(
                        SendNotificationJob::JOB_TYPE,
                        (new SendNotificationJob($request, $attemptCount + 1, $uuid, $currentChannelIndex, $currentProviderIndex))->toPayload(),
                    );
                    return;
                }

                if ($this->shouldFailoverProvider($result->failureType) && $currentProviderIndex < count($providers) - 1) {
                    continue;
                }

                if (!$this->shouldFallbackToNextChannel($policy, $result->failureType, $fallbackEnabled)) {
                    return;
                }

                break;
            }

            $providerIndex = 0;
        }
    }

    private function sendResolvedProvider(NotificationChannel $channel, NotificationMessage $message, ResolvedProvider $resolved): NotificationProviderResult
    {
        try {
            return match ($channel) {
                NotificationChannel::EMAIL => $this->sendEmail($message, $resolved),
                NotificationChannel::SMS => $this->sendSms($message, $resolved),
                NotificationChannel::WHATSAPP => $this->sendWhatsApp($message, $resolved),
                NotificationChannel::IN_APP => $this->inAppProvider->send($message),
            };
        } catch (\RuntimeException $e) {
            return new NotificationProviderResult(
                success: false,
                errorMessage: $e->getMessage(),
                failureType: FailureType::CONFIGURATION_ERROR
            );
        } catch (\Throwable $e) {
            return new NotificationProviderResult(
                success: false,
                errorMessage: $e->getMessage(),
                failureType: FailureType::TEMPORARY_FAILURE
            );
        }
    }

    private function sendEmail(NotificationMessage $message, ResolvedProvider $resolved): NotificationProviderResult
    {
        if (empty($message->recipient->email)) {
            return new NotificationProviderResult(false, null, 'Recipient does not have an email address.', FailureType::RECIPIENT_INVALID);
        }

        if (!$resolved->client instanceof EmailGatewayClientInterface) {
            throw new \RuntimeException('Resolved email provider client is invalid.');
        }

        $result = $resolved->client->sendEmail(new EmailPayload(
            toEmail: $message->recipient->email,
            subject: $message->subject,
            body: $message->body,
            isHtml: $message->isHtml,
            toName: $message->recipient->name,
            fromEmail: (string) $this->settings->get('notification.default_from_email', 'no-reply@workeddy.com'),
            fromName: (string) $this->settings->get('notification.default_from_name', 'BrowseMX'),
            replyToEmail: ((string) $this->settings->get('notification.default_reply_to_email', '')) !== '' ? (string) $this->settings->get('notification.default_reply_to_email', '') : null,
            replyToName: ((string) $this->settings->get('notification.default_reply_to_name', '')) !== '' ? (string) $this->settings->get('notification.default_reply_to_name', '') : null,
            metadata: $message->metadata
        ), $resolved->entry);

        return new NotificationProviderResult($result->success, $result->providerMessageId, $result->errorMessage, $result->failureType);
    }

    private function sendSms(NotificationMessage $message, ResolvedProvider $resolved): NotificationProviderResult
    {
        if (empty($message->recipient->phone)) {
            return new NotificationProviderResult(false, null, 'Recipient does not have a phone number.', FailureType::RECIPIENT_INVALID);
        }

        if (!$resolved->client instanceof SmsGatewayClientInterface) {
            throw new \RuntimeException('Resolved SMS provider client is invalid.');
        }

        $result = $resolved->client->sendSms(new SmsPayload(
            to: $message->recipient->phone,
            body: $message->body,
            metadata: $message->metadata
        ), $resolved->entry);

        return new NotificationProviderResult($result->success, $result->providerMessageId, $result->errorMessage, $result->failureType);
    }

    private function sendWhatsApp(NotificationMessage $message, ResolvedProvider $resolved): NotificationProviderResult
    {
        if (empty($message->recipient->phone)) {
            return new NotificationProviderResult(false, null, 'Recipient does not have a phone number.', FailureType::RECIPIENT_INVALID);
        }

        if (!$resolved->client instanceof WhatsAppGatewayClientInterface) {
            throw new \RuntimeException('Resolved WhatsApp provider client is invalid.');
        }

        $result = $resolved->client->sendWhatsApp(new WhatsAppPayload(
            to: $message->recipient->phone,
            body: $message->body,
            metadata: $message->metadata
        ), $resolved->entry);

        return new NotificationProviderResult($result->success, $result->providerMessageId, $result->errorMessage, $result->failureType);
    }

    private function saveLog(
        string $uuid,
        ?int $id,
        ?NotificationDeliveryLog $existingLog,
        NotificationRequest $request,
        NotificationChannel $channel,
        string $providerKey,
        ?string $subject,
        string $body,
        int $attemptCount,
        NotificationProviderResult $result
    ): void {
        $this->logRepository->saveAttempt(new NotificationDeliveryAttempt(
            uuid: $this->uuidGenerator->generate(),
            logUuid: $uuid,
            channel: $channel,
            providerKey: $providerKey,
            attemptCount: $attemptCount,
            status: $result->success ? 'sent' : 'failed',
            failureReason: $result->errorMessage,
            failureType: $result->failureType,
            providerMessageId: $result->providerMessageId,
        ));

        $this->logRepository->save(new NotificationDeliveryLog(
            uuid: $uuid,
            notificationType: $request->type->value,
            recipientType: $request->recipient->recipientType,
            recipientId: $request->recipient->recipientId,
            channel: $channel,
            provider: $providerKey,
            status: $result->success ? 'sent' : 'failed',
            subject: $subject,
            messagePreview: substr($body, 0, 500),
            recipientName: $request->recipient->name,
            recipientEmail: $request->recipient->email,
            recipientPhone: $request->recipient->phone,
            attemptCount: $attemptCount,
            failureReason: $result->errorMessage,
            failureType: $result->failureType,
            providerMessageId: $result->providerMessageId,
            metadataJson: $request->metadata,
            queuedAt: $existingLog?->queuedAt,
            sentAt: $result->success ? $this->clock->now() : null,
            failedAt: !$result->success ? $this->clock->now() : null,
            id: $id,
            createdAt: $existingLog?->createdAt
        ));
    }

    private function shouldRetry(?FailureType $failureType, int $currentAttempt, int $maxAttempts): bool
    {
        if ($currentAttempt >= $maxAttempts) {
            return false;
        }

        return $failureType === FailureType::TEMPORARY_FAILURE || $failureType === FailureType::RATE_LIMITED;
    }

    private function shouldFailoverProvider(?FailureType $failureType): bool
    {
        return in_array($failureType, [
            FailureType::CONFIGURATION_ERROR,
            FailureType::TEMPORARY_FAILURE,
            FailureType::RATE_LIMITED,
            FailureType::PERMANENT_FAILURE,
            FailureType::PROVIDER_REJECTED,
        ], true);
    }

    private function shouldFallbackToNextChannel(NotificationChannelPolicy $policy, ?FailureType $failureType, bool $fallbackEnabled): bool
    {
        if (!$fallbackEnabled) {
            return false;
        }

        return match ($policy->fallbackMode) {
            NotificationFallbackMode::NEVER => false,
            NotificationFallbackMode::ON_FAILURE => in_array($failureType, [
                FailureType::CONFIGURATION_ERROR,
                FailureType::TEMPORARY_FAILURE,
                FailureType::RATE_LIMITED,
                FailureType::PERMANENT_FAILURE,
                FailureType::RECIPIENT_INVALID,
                FailureType::PROVIDER_REJECTED,
                null,
            ], true),
            NotificationFallbackMode::ON_PERMANENT_FAILURE => in_array($failureType, [
                FailureType::CONFIGURATION_ERROR,
                FailureType::PERMANENT_FAILURE,
                FailureType::RECIPIENT_INVALID,
                FailureType::PROVIDER_REJECTED,
            ], true),
            NotificationFallbackMode::ON_UNAVAILABLE => in_array($failureType, [
                FailureType::CONFIGURATION_ERROR,
                FailureType::RECIPIENT_INVALID,
                null,
            ], true),
        };
    }
}
