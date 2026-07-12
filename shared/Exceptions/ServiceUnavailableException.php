<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class ServiceUnavailableException extends DomainException
{
    public function __construct(string $message = 'Service unavailable', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
