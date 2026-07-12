<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class NotFoundException extends DomainException
{
    public function __construct(string $message = 'Not found', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
