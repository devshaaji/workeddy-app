<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Providers;

use WorkEddy\Modules\Notification\Contracts\NotificationProviderInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationMessage;
use WorkEddy\Modules\Notification\Domain\NotificationProviderResult;
use WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType;
use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\WhatsAppPayload;
use WorkEddy\Modules\Notification\Infrastructure\Clients\ProviderRouter;
use WorkEddy\Modules\Notification\Infrastructure\Clients\WhatsAppGatewayClientInterface;

final class WhatsAppNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private readonly ProviderRouter $providerRouter
    ) {}

    public function channel(): string
    {
        return NotificationChannel::WHATSAPP->value;
    }

    public function send(NotificationMessage $message): NotificationProviderResult
    {
        if (empty($message->recipient->phone)) {
            return new NotificationProviderResult(false, null, 'Recipient does not have a phone number.', FailureType::RECIPIENT_INVALID);
        }

        try {
            $resolved = $this->providerRouter->resolve('whatsapp');

            $payload = new WhatsAppPayload(
                to: $message->recipient->phone,
                body: $message->body,
                metadata: $message->metadata
            );

            /** @var WhatsAppGatewayClientInterface $client */
            $client = $resolved->client;
            $result = $client->sendWhatsApp($payload, $resolved->entry);

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
