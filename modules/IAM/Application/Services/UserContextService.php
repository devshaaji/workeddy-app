<?php

/**
 * UserContextService — concrete implementation of IUserContextService.
 *
 * Allows other modules to resolve a UserContext by user ID
 * (e.g., for job payloads that carry a user ID).
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserContextService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Clock\SystemClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class UserContextService implements IUserContextService
{
    public function __construct(
        private readonly IUserRepository      $userRepo,
        private readonly UserContextFactory $contextFactory,
        private readonly ?IClock $clock = null,
    ) {}

    /**
     * Build a UserContext for a given user ID.
     *
     * Used by job handlers and system processes that need to impersonate a user.
     *
     * @throws NotFoundException If user not found.
     */
    public function getById(int|string $userId): UserContext
    {
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User', $userId);
        }
        if (!$user->isActive()) {
            throw new ForbiddenException('Account is not active for context resolution.');
        }

        return $this->contextFactory->fromUser($user, $this->clock()->now()->format('c'));
    }

    private function clock(): IClock
    {
        return $this->clock ?? new SystemClock();
    }
}
