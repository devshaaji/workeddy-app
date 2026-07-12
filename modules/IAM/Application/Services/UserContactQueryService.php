<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Application\DTOs\UserContactDTO;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserContactQueryService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class UserContactQueryService implements IUserContactQueryService
{
    public function __construct(
        private readonly IUserRepository $userRepository,
    ) {}

    public function getByUserId(int $userId): UserContactDTO
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User', $userId);
        }

        return new UserContactDTO(
            userId: $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            isActive: $user->getStatus() === UserStatus::ACTIVE,
            phone: $user->getPhone(),
        );
    }
}
