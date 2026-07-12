<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class ForbiddenException extends DomainException
{
    public function __construct(string $message = 'Forbidden', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
