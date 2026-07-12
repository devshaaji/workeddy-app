<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Application\DTOs;

final class PublishedContentPage
{
    /**
     * @param list<PublishedContentSection> $sections
     * @param list<PublishedContentReference> $references
     * @param list<PublishedContentImage> $images
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $audience,
        public readonly string $templateKey,
        public readonly array $sections,
        public readonly array $references,
        public readonly array $images,
        public readonly string $revisionUuid,
        public readonly \DateTimeImmutable $publishedAt,
        public readonly string $snapshotHash,
    ) {}
}
