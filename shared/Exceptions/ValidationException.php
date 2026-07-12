<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class ValidationException extends DomainException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private readonly array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message, 422);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
