<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients\Payload;

final class WhatsAppPayload
{
    public function __construct(
        public readonly string $to,
        public readonly string $body,
        public readonly ?string $from = null,
        public readonly array $metadata = []
    ) {}
}
