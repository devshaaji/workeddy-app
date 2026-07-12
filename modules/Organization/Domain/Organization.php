<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Domain;

final class Organization
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly string $name,
        private readonly string $slug,
        private readonly string $status = 'active',
        private readonly ?string $contactEmail = null,
        private readonly ?string $phone = null,
        private readonly ?string $createdAt = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
