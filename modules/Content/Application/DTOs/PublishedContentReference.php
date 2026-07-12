<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Application\DTOs;

final class PublishedContentReference
{
    public function __construct(
        public readonly ?string $sectionKey,
        public readonly string $title,
        public readonly ?string $author,
        public readonly ?string $year,
        public readonly ?string $url,
        public readonly ?string $citation,
        public readonly int $displayOrder,
    ) {}
}
