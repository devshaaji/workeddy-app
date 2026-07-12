<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound\Adapters;

final class SseInboundAdapter
{
    public function isConfigured(): bool
    {
        return false;
    }
}
