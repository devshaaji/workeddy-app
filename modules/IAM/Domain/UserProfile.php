<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain;

final class UserProfile
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly int $userId,
        private string $fullName,
        private ?string $phone = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function update(string $fullName, ?string $phone): void
    {
        $this->fullName = $fullName;
        $this->phone = $phone;
    }
}
