<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain\Contracts;

use WorkEddy\Modules\IAM\Application\DTOs\UserContactDTO;

interface IUserContactQueryService
{
    /** @throws \WorkEddy\Shared\Exceptions\NotFoundException */
    public function getByUserId(int $userId): UserContactDTO;
}
