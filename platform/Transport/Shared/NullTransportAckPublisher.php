<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

use WorkEddy\Platform\Transport\Inbound\TransportInboxMessage;
use WorkEddy\Platform\Transport\Inbound\TransportProcessingResult;

final class NullTransportAckPublisher implements TransportAckPublisherInterface
{
    public function publishProcessedAck(TransportInboxMessage $message, TransportProcessingResult $result): void {}
}
