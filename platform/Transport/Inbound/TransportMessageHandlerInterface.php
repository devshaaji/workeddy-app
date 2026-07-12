<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

interface TransportMessageHandlerInterface
{
    public function supports(string $topic): bool;

    public function handle(TransportInboxMessage $message): TransportProcessingResult;
}
