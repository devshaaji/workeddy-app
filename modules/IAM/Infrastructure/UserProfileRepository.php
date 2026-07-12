<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserProfileRepository;
use WorkEddy\Modules\IAM\Domain\UserProfile;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class UserProfileRepository implements IUserProfileRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(UserProfile $profile): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->connection->insert('user_profiles', [
            'uuid' => $profile->getUuid() !== '' ? $profile->getUuid() : UuidSupport::generate(),
            'user_id' => $profile->getUserId(),
            'full_name' => $profile->getFullName(),
            'phone' => $profile->getPhone(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(UserProfile $profile): void
    {
        $this->connection->update('user_profiles', [
            'full_name' => $profile->getFullName(),
            'phone' => $profile->getPhone(),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], ['id' => (int) $profile->getId()]);
    }

    public function findByUserId(int|string $userId): ?UserProfile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM user_profiles WHERE user_id = ?',
            [(int) $userId],
        );

        if ($row === false) {
            return null;
        }

        return new UserProfile(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            userId: (int) $row['user_id'],
            fullName: (string) $row['full_name'],
            phone: $row['phone'] ?? null,
        );
    }
}
