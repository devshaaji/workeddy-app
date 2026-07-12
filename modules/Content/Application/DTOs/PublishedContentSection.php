<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Application\DTOs;

final class PublishedContentSection
{
    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    public function __construct(
        public readonly string $sectionKey,
        public readonly string $heading,
        public readonly array $blocks,
        public readonly int $displayOrder,
        public readonly string $plainText,
    ) {}
}
