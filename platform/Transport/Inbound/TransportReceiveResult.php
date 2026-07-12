<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class TransportReceiveResult
{
    public function __construct(
        public readonly bool $success,
        public readonly bool $duplicate,
        public readonly bool $rejected,
        public readonly ?int $inboxMessageId,
        public readonly ?string $inboxUuid,
        public readonly string $status,
        public readonly ?string $errorMessage,
        public readonly ?TransportInboxMessage $message = null,
    ) {}
}
