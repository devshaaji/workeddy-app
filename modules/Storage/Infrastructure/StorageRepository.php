<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageRepository;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class StorageRepository implements IStorageRepository
{
    /**
     * Known cross-module references to a stored file. Each entry is a
     * [table, column] pair. Storage remains the owning module; other modules
     * that persist a `storage_file_uuid`-style pointer are listed here so
     * destructive deletes can be blocked safely. Extend this list whenever a
     * new module starts referencing uploads.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const USAGE_PROBES = [
        ['content_media', 'storage_file_uuid'],
        ['assessment_videos', 'storage_file_uuid'],
        ['assessment_videos', 'thumbnail_storage_file_uuid'],
        ['assessment_videos', 'pose_video_storage_file_uuid'],
        ['assessment_videos', 'blurred_storage_file_uuid'],
    ];

    public function __construct(private readonly Connection $conn) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): StoredFileDTO
    {
        $now = date('Y-m-d H:i:s');
        $this->conn->insert('uploads', [
            'uuid' => $data['uuid'],
            'disk' => $data['disk'],
            'visibility' => $data['visibility'],
            'status' => $data['status'] ?? 'active',
            'path' => $data['path'],
            'owner_type' => $data['owner_type'],
            'owner_uuid' => $data['owner_uuid'],
            'field_name' => $data['field_name'],
            'original_name' => $data['original_name'],
            'mime_type' => $data['mime_type'] ?? null,
            'extension' => $data['extension'] ?? null,
            'size_bytes' => (int) ($data['size_bytes'] ?? 0),
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'checksum_sha256' => $data['checksum_sha256'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? null,
            'deletion_requested_at' => $data['deletion_requested_at'] ?? null,
            'deletion_requested_by' => $data['deletion_requested_by'] ?? null,
            'created_at' => $now,
            'updated_at' => null,
        ]);

        return $this->findByUuid((string) $data['uuid'])
            ?? throw new \RuntimeException('Stored file metadata was created but could not be read.');
    }

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): ?StoredFileDTO
    {
        $sql = $this->baseSelect() . ' WHERE u.uuid = ?';
        if (!$includePendingDeletion) {
            $sql .= " AND u.status <> 'pending_delete'";
        }
        $sql .= ' LIMIT 1';

        $row = $this->conn->fetchAssociative($sql, [$uuid]);

        return $row ? StoredFileDTO::fromRow($row) : null;
    }

    /** @return StoredFileDTO[] */
    public function list(array $filters = []): array
    {
        [$where, $params] = $this->where($filters);
        $params['limit'] = max(1, min(1000, (int) ($filters['limit'] ?? 100)));
        $params['offset'] = max(0, (int) ($filters['offset'] ?? 0));

        $rows = $this->conn->fetchAllAssociative(
            $this->baseSelect() . ' WHERE ' . $where . ' ORDER BY ' . $this->orderBy($filters) . ' LIMIT :limit OFFSET :offset',
            $params,
            ['limit' => \PDO::PARAM_INT, 'offset' => \PDO::PARAM_INT],
        );

        return array_map(static fn(array $row): StoredFileDTO => StoredFileDTO::fromRow($row), $rows);
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->where($filters);

        return (int) $this->conn->fetchOne('SELECT COUNT(*) FROM uploads u WHERE ' . $where, $params);
    }

    public function summary(array $filters = []): array
    {
        [$where, $params] = $this->where($filters);

        $totals = $this->conn->fetchAssociative(
            'SELECT COUNT(*) AS total_files, COALESCE(SUM(size_bytes), 0) AS total_bytes FROM uploads u WHERE ' . $where,
            $params,
        ) ?: ['total_files' => 0, 'total_bytes' => 0];

        $rows = $this->conn->fetchAllAssociative(
            'SELECT mime_type, extension, COUNT(*) AS cnt, COALESCE(SUM(size_bytes), 0) AS bytes '
            . 'FROM uploads u WHERE ' . $where . ' GROUP BY mime_type, extension',
            $params,
        );

        $byCategory = [
            'image' => ['count' => 0, 'bytes' => 0],
            'document' => ['count' => 0, 'bytes' => 0],
            'video' => ['count' => 0, 'bytes' => 0],
            'audio' => ['count' => 0, 'bytes' => 0],
            'archive' => ['count' => 0, 'bytes' => 0],
            'other' => ['count' => 0, 'bytes' => 0],
        ];

        foreach ($rows as $row) {
            $dto = StoredFileDTO::fromRow([
                'uuid' => '',
                'path' => '',
                'owner_type' => '',
                'owner_uuid' => '',
                'field_name' => '',
                'original_name' => '',
                'mime_type' => $row['mime_type'],
                'extension' => $row['extension'],
            ]);
            $category = $dto->category();
            $byCategory[$category]['count'] += (int) $row['cnt'];
            $byCategory[$category]['bytes'] += (int) $row['bytes'];
        }

        return [
            'totalFiles' => (int) $totals['total_files'],
            'totalBytes' => (int) $totals['total_bytes'],
            'byCategory' => $byCategory,
        ];
    }

    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO
    {
        $file = $this->findByUuid($uuid, true);
        if ($file === null) {
            throw new NotFoundException('Upload ' . $uuid);
        }
        if ($file->status === 'pending_delete') {
            return $file;
        }

        $now = date('Y-m-d H:i:s');
        $this->conn->update('uploads', [
            'status' => 'pending_delete',
            'deletion_requested_at' => $now,
            'deletion_requested_by' => $actorId,
            'updated_at' => $now,
        ], ['uuid' => $uuid]);

        $updated = $this->findByUuid($uuid, true);

        return $updated ?? throw new \RuntimeException('Stored file was updated but could not be read.');
    }

    public function restore(string $uuid): StoredFileDTO
    {
        $file = $this->findByUuid($uuid, true);
        if ($file === null) {
            throw new NotFoundException('Upload ' . $uuid);
        }
        if ($file->status !== 'pending_delete') {
            return $file;
        }

        $now = date('Y-m-d H:i:s');
        $this->conn->update('uploads', [
            'status' => 'active',
            'deletion_requested_at' => null,
            'deletion_requested_by' => null,
            'updated_at' => $now,
        ], ['uuid' => $uuid]);

        $updated = $this->findByUuid($uuid, true);

        return $updated ?? throw new \RuntimeException('Stored file was updated but could not be read.');
    }

    public function delete(string $uuid): void
    {
        $deleted = $this->conn->delete('uploads', ['uuid' => $uuid]);
        if ($deleted < 1) {
            throw new NotFoundException('Upload ' . $uuid);
        }
    }

    public function usageCount(string $uuid): int
    {
        $total = 0;
        foreach (self::USAGE_PROBES as [$table, $column]) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $total += (int) $this->conn->fetchOne(
                'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?',
                [$uuid],
            );
        }

        return $total;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            $cache[$table] = $this->conn->createSchemaManager()->tablesExist([$table]);
        }

        return $cache[$table];
    }

    private function baseSelect(): string
    {
        return 'SELECT u.*, us.full_name AS uploaded_by_name FROM uploads u LEFT JOIN users us ON us.id = u.uploaded_by';
    }

    /** @param array<string, mixed> $filters */
    private function orderBy(array $filters): string
    {
        $sort = strtolower((string) ($filters['sort'] ?? 'date'));
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $column = match ($sort) {
            'name' => 'u.original_name',
            'size' => 'u.size_bytes',
            'oldest' => 'u.created_at',
            'newest', 'date' => 'u.created_at',
            default => 'u.created_at',
        };

        return $column . ' ' . $direction . ', u.id ' . $direction;
    }

    /** @param array<string, mixed> $filters @return array{0: string, 1: array<string, mixed>} */
    private function where(array $filters): array
    {
        $where = '1 = 1';
        $params = [];

        foreach (['owner_type', 'owner_uuid', 'visibility', 'disk', 'status'] as $field) {
            if (isset($filters[$field]) && trim((string) $filters[$field]) !== '') {
                $where .= " AND u.{$field} = :{$field}";
                $params[$field] = trim((string) $filters[$field]);
            }
        }

        if (empty($filters['include_pending_deletion'])) {
            $where .= " AND u.status <> 'pending_delete'";
        }

        if (isset($filters['uploaded_by']) && $filters['uploaded_by'] !== '' && $filters['uploaded_by'] !== null) {
            $where .= ' AND u.uploaded_by = :uploaded_by';
            $params['uploaded_by'] = (int) $filters['uploaded_by'];
        }

        if (isset($filters['date_from']) && trim((string) $filters['date_from']) !== '') {
            $where .= ' AND u.created_at >= :date_from';
            $params['date_from'] = trim((string) $filters['date_from']) . ' 00:00:00';
        }

        if (isset($filters['date_to']) && trim((string) $filters['date_to']) !== '') {
            $where .= ' AND u.created_at <= :date_to';
            $params['date_to'] = trim((string) $filters['date_to']) . ' 23:59:59';
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $where .= ' AND (u.original_name LIKE :search OR u.field_name LIKE :search OR u.owner_type LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (isset($filters['category']) && trim((string) $filters['category']) !== '') {
            $categorySql = $this->categorySql((string) $filters['category']);
            if ($categorySql !== null) {
                $where .= ' AND (' . $categorySql . ')';
            }
        }

        return [$where, $params];
    }

    private function categorySql(string $category): ?string
    {
        return match (strtolower($category)) {
            'image' => "u.mime_type LIKE 'image/%'",
            'video' => "u.mime_type LIKE 'video/%'",
            'audio' => "u.mime_type LIKE 'audio/%'",
            'archive' => "u.extension IN ('zip','rar','7z','tar','gz','bz2')",
            'document' => "u.mime_type IN ('application/pdf','text/plain','text/csv') "
                . "OR u.mime_type LIKE '%msword%' OR u.mime_type LIKE '%wordprocessingml%' "
                . "OR u.mime_type LIKE '%spreadsheetml%' OR u.mime_type LIKE '%presentationml%' "
                . "OR u.mime_type LIKE '%ms-excel%' OR u.mime_type LIKE '%ms-powerpoint%' "
                . "OR u.extension IN ('pdf','doc','docx','xls','xlsx','ppt','pptx','csv','txt')",
            default => null,
        };
    }
}
