<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain;

final class ContentMedia
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $storageFileUuid,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly ?string $extension,
        public readonly int $sizeBytes,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $defaultAltText,
        public readonly ?string $defaultCaption,
        public readonly string $status,
        public readonly ?int $uploadedBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $archivedAt,
    ) {}
}
