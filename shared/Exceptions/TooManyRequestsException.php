<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class TooManyRequestsException extends DomainException
{
    public function __construct(string $message = 'Too many requests', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
