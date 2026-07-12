<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients;

use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\WhatsAppPayload;
use WorkEddy\Modules\Notification\Domain\ProviderEntry;

interface WhatsAppGatewayClientInterface
{
    public function sendWhatsApp(WhatsAppPayload $payload, ProviderEntry $provider): ProviderSendResult;
}
