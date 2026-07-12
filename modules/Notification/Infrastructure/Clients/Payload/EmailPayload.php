<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients\Payload;

final class EmailPayload
{
    public function __construct(
        public readonly string $toEmail,
        public readonly string $subject,
        public readonly string $body,
        public readonly bool $isHtml = true,
        public readonly ?string $toName = null,
        public readonly ?string $fromEmail = null,
        public readonly ?string $fromName = null,
        public readonly ?string $replyToEmail = null,
        public readonly ?string $replyToName = null,
        public readonly array $metadata = []
    ) {}
}
