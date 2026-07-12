<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Application\DTOs;

final class PublishedContentImage
{
    public function __construct(
        public readonly string $mediaUuid,
        public readonly string $storageFileUuid,
        public readonly string $altText,
        public readonly ?string $caption,
        public readonly string $display,
        public readonly ?int $width,
        public readonly ?int $height,
    ) {}
}
