<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

/** External API / gateway failure → HTTP 502. */
class GatewayException extends DomainException
{
    private string $gatewayName;

    public function __construct(string $gatewayName, string $message = 'Gateway error')
    {
        parent::__construct("{$gatewayName}: {$message}", 502);
        $this->gatewayName = $gatewayName;
    }

    public function getGatewayName(): string
    {
        return $this->gatewayName;
    }
}
