<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Application\DTOs;

final class StoreUploadedFileRequest
{
    /** @param array<string, mixed> $file */
    public function __construct(
        public readonly array $file,
        public readonly string $ownerType,
        public readonly string $ownerUuid,
        public readonly string $fieldName,
        public readonly ?string $visibility = null,
        public readonly ?string $disk = null,
        public readonly ?int $actorId = null,
        /** @var string[]|null */
        public readonly ?array $allowedExtensions = null,
        /** @var string[]|null */
        public readonly ?array $allowedMimeTypes = null,
        public readonly ?int $maxUploadBytes = null,
    ) {}
}
