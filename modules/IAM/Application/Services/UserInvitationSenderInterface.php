<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\User;

interface UserInvitationSenderInterface
{
    public function sendPasswordSetup(User $user, ?string $actorId = null): void;
}
