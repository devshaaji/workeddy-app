<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationPreference
{
    public function __construct(
        public readonly string $recipientType,
        public readonly string $recipientId,
        public readonly bool $inAppEnabled = true,
        public readonly bool $emailEnabled = true,
        public readonly bool $smsEnabled = true,
        public readonly bool $whatsAppEnabled = true,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null,
        public readonly ?int $id = null,
    ) {}

    public static function defaults(string $recipientType, string $recipientId): self
    {
        return new self(
            recipientType: $recipientType,
            recipientId: $recipientId,
            inAppEnabled: true,
            emailEnabled: true,
            smsEnabled: true,
            whatsAppEnabled: true,
        );
    }

    public function allows(NotificationChannel $channel): bool
    {
        return match ($channel) {
            NotificationChannel::IN_APP => true,
            NotificationChannel::EMAIL => $this->emailEnabled,
            NotificationChannel::SMS => $this->smsEnabled,
            NotificationChannel::WHATSAPP => $this->whatsAppEnabled,
        };
    }

    /**
     * @return array<string, bool>
     */
    public function channels(): array
    {
        return [
            NotificationChannel::IN_APP->value => true,
            NotificationChannel::EMAIL->value => $this->emailEnabled,
            NotificationChannel::SMS->value => $this->smsEnabled,
            NotificationChannel::WHATSAPP->value => $this->whatsAppEnabled,
        ];
    }
}
