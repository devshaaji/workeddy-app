<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class ConflictException extends DomainException
{
    public function __construct(string $message = 'Conflict', int $code = 409)
    {
        parent::__construct($message, $code);
    }
}
