<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients;

use WorkEddy\Modules\Notification\Infrastructure\Clients\Payload\SmsPayload;
use WorkEddy\Modules\Notification\Domain\ProviderEntry;

interface SmsGatewayClientInterface
{
    public function sendSms(SmsPayload $payload, ProviderEntry $provider): ProviderSendResult;
}
