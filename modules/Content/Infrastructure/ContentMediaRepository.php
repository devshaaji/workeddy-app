<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Content\Domain\ContentMedia;
use WorkEddy\Modules\Content\Domain\Contracts\IContentMediaRepository;

final class ContentMediaRepository implements IContentMediaRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(ContentMedia $media): ContentMedia
    {
        $this->connection->insert('content_media', [
            'uuid' => $media->uuid,
            'storage_file_uuid' => $media->storageFileUuid,
            'original_name' => $media->originalName,
            'mime_type' => $media->mimeType,
            'extension' => $media->extension,
            'size_bytes' => $media->sizeBytes,
            'width' => $media->width,
            'height' => $media->height,
            'default_alt_text' => $media->defaultAltText,
            'default_caption' => $media->defaultCaption,
            'status' => $media->status,
            'uploaded_by' => $media->uploadedBy,
            'created_at' => $media->createdAt,
            'updated_at' => $media->updatedAt,
            'archived_at' => $media->archivedAt,
        ]);

        return new ContentMedia(
            id: (int) $this->connection->lastInsertId(),
            uuid: $media->uuid,
            storageFileUuid: $media->storageFileUuid,
            originalName: $media->originalName,
            mimeType: $media->mimeType,
            extension: $media->extension,
            sizeBytes: $media->sizeBytes,
            width: $media->width,
            height: $media->height,
            defaultAltText: $media->defaultAltText,
            defaultCaption: $media->defaultCaption,
            status: $media->status,
            uploadedBy: $media->uploadedBy,
            createdAt: $media->createdAt,
            updatedAt: $media->updatedAt,
            archivedAt: $media->archivedAt,
        );
    }

    public function update(ContentMedia $media): void
    {
        if ($media->id === null) {
            throw new \InvalidArgumentException('Cannot update content media without an internal id.');
        }

        $this->connection->update('content_media', [
            'default_alt_text' => $media->defaultAltText,
            'default_caption' => $media->defaultCaption,
            'status' => $media->status,
            'updated_at' => $media->updatedAt,
            'archived_at' => $media->archivedAt,
        ], ['id' => $media->id]);
    }

    public function findByUuid(string $uuid): ?ContentMedia
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_media WHERE uuid = ? LIMIT 1', [$uuid]);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function listSelectable(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM content_media WHERE status <> ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
            ['archived', $limit, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT],
        );

        return array_map(fn(array $row): ContentMedia => $this->hydrate($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ContentMedia
    {
        return new ContentMedia(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) $row['uuid'],
            storageFileUuid: (string) $row['storage_file_uuid'],
            originalName: (string) $row['original_name'],
            mimeType: (string) $row['mime_type'],
            extension: isset($row['extension']) ? (string) $row['extension'] : null,
            sizeBytes: (int) ($row['size_bytes'] ?? 0),
            width: isset($row['width']) ? (int) $row['width'] : null,
            height: isset($row['height']) ? (int) $row['height'] : null,
            defaultAltText: isset($row['default_alt_text']) ? (string) $row['default_alt_text'] : null,
            defaultCaption: isset($row['default_caption']) ? (string) $row['default_caption'] : null,
            status: (string) ($row['status'] ?? 'active'),
            uploadedBy: isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
            createdAt: self::stringDate($row['created_at'] ?? null),
            updatedAt: self::stringDate($row['updated_at'] ?? null),
            archivedAt: self::nullableStringDate($row['archived_at'] ?? null),
        );
    }

    private static function stringDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private static function nullableStringDate(mixed $value): ?string
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
