<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients;

use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\EmailPayload;
use WorkEddy\Modules\Notification\Domain\ProviderEntry;

interface EmailGatewayClientInterface
{
    public function sendEmail(EmailPayload $payload, ProviderEntry $provider): ProviderSendResult;
}
