<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Exceptions;

final class WrongScopeException extends ForbiddenException
{
    public function __construct(
        string $message = 'You do not have access to this page in the current organization scope.',
        private readonly ?string $organizationName = null,
        private readonly ?string $organizationUuid = null,
        private readonly ?string $suggestedAction = 'Switch to the correct organization scope to continue.',
    ) {
        parent::__construct($message, 403);
    }

    public function organizationName(): ?string
    {
        return $this->organizationName;
    }

    public function organizationUuid(): ?string
    {
        return $this->organizationUuid;
    }

    public function suggestedAction(): ?string
    {
        return $this->suggestedAction;
    }
}
