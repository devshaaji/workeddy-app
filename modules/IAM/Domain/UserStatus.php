<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain;

/**
 * Canonical user account status.
 *
 * Transitions:
 *   PENDING → ACTIVE (admin activates)
 *   ACTIVE → SUSPENDED (admin suspends)
 *   SUSPENDED → ACTIVE (admin reactivates)
 *   ACTIVE/PENDING/SUSPENDED → DELETED (admin soft delete)
 *
 * DELETED is retained for audit/reference and cannot login.
 */
enum UserStatus: string
{
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
    case PENDING   = 'pending';
    case DELETED   = 'deleted';

    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }
}
