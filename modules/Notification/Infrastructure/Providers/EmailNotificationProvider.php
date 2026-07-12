<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Providers;

use WorkEddy\Modules\Notification\Contracts\NotificationProviderInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationMessage;
use WorkEddy\Modules\Notification\Domain\NotificationProviderResult;
use WorkEddy\Modules\Notification\Infrastructure\Clients\EmailGatewayClientInterface;
use WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\EmailPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\ProviderRouter;
use WorkEddy\Platform\Settings\SettingsService;

final class EmailNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private readonly ProviderRouter $providerRouter,
        private readonly SettingsService $settings
    ) {}

    public function channel(): string
    {
        return NotificationChannel::EMAIL->value;
    }

    public function send(NotificationMessage $message): NotificationProviderResult
    {
        if (empty($message->recipient->email)) {
            return new NotificationProviderResult(false, null, 'Recipient does not have an email address.', FailureType::RECIPIENT_INVALID);
        }

        try {
            $resolved = $this->providerRouter->resolve('email');

            $defaultFromEmail = (string) $this->settings->get('notification.default_from_email', 'noreply@browsemx.local');
            $defaultFromName = (string) $this->settings->get('notification.default_from_name', 'BrowseMX');

            $payload = new EmailPayload(
                toEmail: $message->recipient->email,
                subject: $message->subject,
                body: $message->body,
                isHtml: $message->isHtml,
                toName: $message->recipient->name,
                fromEmail: $defaultFromEmail,
                fromName: $defaultFromName,
                metadata: $message->metadata
            );

            /** @var EmailGatewayClientInterface $client */
            $client = $resolved->client;
            $result = $client->sendEmail($payload, $resolved->entry);

            return new NotificationProviderResult(
                success: $result->success,
                providerMessageId: $result->providerMessageId,
                errorMessage: $result->errorMessage,
                failureType: $result->failureType
            );
        } catch (\Throwable $e) {
            return new NotificationProviderResult(
                success: false,
                errorMessage: $e->getMessage(),
                failureType: FailureType::TEMPORARY_FAILURE
            );
        }
    }
}
