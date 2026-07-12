<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class AuthenticationException extends DomainException
{
    public function __construct(string $message = 'Unauthorized', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}
