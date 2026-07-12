<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Session;

interface ISessionService
{
    public function getUserContext(): ?UserContext;

    public function setUserContext(UserContext $context): void;

    public function regenerate(): void;

    public function destroy(): void;

    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;
}
