<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Domain\Contracts;

use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;

interface IStorageService
{
    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO;

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO;

    /**
     * @param array<string, mixed> $filters
     * @return StoredFileDTO[]
     */
    public function list(array $filters = []): array;

    /** @param array<string, mixed> $filters */
    public function count(array $filters = []): int;

    /**
     * @param array<string, mixed> $filters
     * @return array{totalFiles: int, totalBytes: int, byCategory: array<string, array{count: int, bytes: int}>}
     */
    public function summary(array $filters = []): array;

    public function read(string $uuid): string;

    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO;

    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO;

    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO;

    public function usageCount(string $uuid): int;
}
