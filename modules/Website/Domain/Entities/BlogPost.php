<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Domain\Entities;

final class BlogPost
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $content,
        public readonly ?string $excerpt,
        public readonly ?int $authorId,
        public readonly string $status,
        public readonly ?\DateTimeImmutable $publishedAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}
}
