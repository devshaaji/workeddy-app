<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

interface TransportMessageHandlerProviderInterface
{
    /**
     * @return list<class-string<TransportMessageHandlerInterface>>
     */
    public function getTransportMessageHandlers(): array;
}
