<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class MethodNotAllowedException extends DomainException
{
    public function __construct(string $message = 'Method not allowed', int $code = 405)
    {
        parent::__construct($message, $code);
    }
}
