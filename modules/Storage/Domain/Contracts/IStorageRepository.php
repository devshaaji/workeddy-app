<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Domain\Contracts;

use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;

/**
 * @phpstan-type StorageFilters array{
 *     owner_type?: string,
 *     owner_uuid?: string,
 *     visibility?: string,
 *     disk?: string,
 *     search?: string,
 *     status?: string,
 *     category?: string,
 *     uploaded_by?: int,
 *     date_from?: string,
 *     date_to?: string,
 *     sort?: string,
 *     direction?: string,
 *     include_pending_deletion?: bool,
 *     limit?: int,
 *     offset?: int,
 * }
 */
interface IStorageRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): StoredFileDTO;

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): ?StoredFileDTO;

    /**
     * @param StorageFilters $filters
     * @return StoredFileDTO[]
     */
    public function list(array $filters = []): array;

    /** @param StorageFilters $filters */
    public function count(array $filters = []): int;

    /**
     * Aggregate counts and byte totals for the storage summary panel.
     * A single grouped query — never a per-record scan.
     *
     * @param StorageFilters $filters
     * @return array{
     *     totalFiles: int,
     *     totalBytes: int,
     *     byCategory: array<string, array{count: int, bytes: int}>,
     * }
     */
    public function summary(array $filters = []): array;

    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO;

    public function restore(string $uuid): StoredFileDTO;

    public function delete(string $uuid): void;

    /**
     * Count of known cross-module references to this file (e.g. Content media,
     * Assessment videos) so the UI/API can block destructive deletes safely.
     */
    public function usageCount(string $uuid): int;
}
