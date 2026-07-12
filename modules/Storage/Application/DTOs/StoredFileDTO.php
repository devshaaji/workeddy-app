<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Application\DTOs;

final class StoredFileDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $disk,
        public readonly string $visibility,
        public readonly string $status,
        public readonly string $path,
        public readonly string $ownerType,
        public readonly string $ownerUuid,
        public readonly string $fieldName,
        public readonly string $originalName,
        public readonly ?string $mimeType,
        public readonly ?string $extension,
        public readonly int $sizeBytes,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $checksumSha256 = null,
        public readonly ?int $uploadedBy = null,
        public readonly ?string $uploadedByName = null,
        public readonly ?string $deletionRequestedAt = null,
        public readonly ?int $deletionRequestedBy = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) $row['uuid'],
            disk: (string) ($row['disk'] ?? 'local'),
            visibility: (string) ($row['visibility'] ?? 'private'),
            status: (string) ($row['status'] ?? 'active'),
            path: (string) $row['path'],
            ownerType: (string) $row['owner_type'],
            ownerUuid: (string) $row['owner_uuid'],
            fieldName: (string) $row['field_name'],
            originalName: (string) $row['original_name'],
            mimeType: isset($row['mime_type']) ? (string) $row['mime_type'] : null,
            extension: isset($row['extension']) ? (string) $row['extension'] : null,
            sizeBytes: (int) ($row['size_bytes'] ?? 0),
            width: isset($row['width']) && $row['width'] !== null ? (int) $row['width'] : null,
            height: isset($row['height']) && $row['height'] !== null ? (int) $row['height'] : null,
            checksumSha256: isset($row['checksum_sha256']) ? (string) $row['checksum_sha256'] : null,
            uploadedBy: isset($row['uploaded_by']) && $row['uploaded_by'] !== null ? (int) $row['uploaded_by'] : null,
            uploadedByName: isset($row['uploaded_by_name']) ? (string) $row['uploaded_by_name'] : null,
            deletionRequestedAt: self::dateString($row['deletion_requested_at'] ?? null),
            deletionRequestedBy: isset($row['deletion_requested_by']) ? (int) $row['deletion_requested_by'] : null,
            createdAt: self::dateString($row['created_at'] ?? null),
            updatedAt: self::dateString($row['updated_at'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includeInternalId = false): array
    {
        $data = [
            'uuid' => $this->uuid,
            'disk' => $this->disk,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'path' => $this->path,
            'owner_type' => $this->ownerType,
            'owner_uuid' => $this->ownerUuid,
            'field_name' => $this->fieldName,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'size_bytes' => $this->sizeBytes,
            'width' => $this->width,
            'height' => $this->height,
            'checksum_sha256' => $this->checksumSha256,
            'uploaded_by' => $this->uploadedBy,
            'deletion_requested_at' => $this->deletionRequestedAt,
            'deletion_requested_by' => $this->deletionRequestedBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        return $includeInternalId ? ['id' => $this->id] + $data : $data;
    }

    /**
     * Best-effort MIME category used for filtering, icon selection, and grouping.
     */
    public function category(): string
    {
        $mime = strtolower((string) $this->mimeType);
        $ext = strtolower((string) $this->extension);

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'], true) || str_contains($mime, 'zip') || str_contains($mime, 'compressed')) {
            return 'archive';
        }
        if (
            $mime === 'application/pdf'
            || str_contains($mime, 'msword')
            || str_contains($mime, 'wordprocessingml')
            || str_contains($mime, 'spreadsheetml')
            || str_contains($mime, 'presentationml')
            || str_contains($mime, 'ms-excel')
            || str_contains($mime, 'ms-powerpoint')
            || $mime === 'text/plain'
            || $mime === 'text/csv'
            || in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt'], true)
        ) {
            return 'document';
        }

        return 'other';
    }

    public static function sizeFormatted(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));

        return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    private static function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
