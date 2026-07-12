<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Modules\IAM\Domain\UserProfile;

interface IUserProfileRepository
{
    public function create(UserProfile $profile): int;
    public function update(UserProfile $profile): void;
    public function findByUserId(int|string $userId): ?UserProfile;
}
