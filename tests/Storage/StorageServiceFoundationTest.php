<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageRepository;
use WorkEddy\Modules\Storage\Infrastructure\StorageService;
use WorkEddy\Modules\Storage\Settings\StorageSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\NotFoundException;

/**
 * Lightweight, DB-free coverage for the admin file manager's new behaviour:
 * category detection, human-readable sizes, trash/restore, and the
 * usage-aware permanent-delete guard described in the Storage boundary docs.
 */
final class StorageServiceFoundationTest extends TestCase
{
    public function test_category_classifies_common_mime_types(): void
    {
        self::assertSame('image', $this->dto(mimeType: 'image/png', extension: 'png')->category());
        self::assertSame('video', $this->dto(mimeType: 'video/mp4', extension: 'mp4')->category());
        self::assertSame('audio', $this->dto(mimeType: 'audio/mpeg', extension: 'mp3')->category());
        self::assertSame('archive', $this->dto(mimeType: 'application/octet-stream', extension: 'zip')->category());
        self::assertSame('document', $this->dto(mimeType: 'application/pdf', extension: 'pdf')->category());
        self::assertSame(
            'document',
            $this->dto(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', extension: 'docx')->category(),
        );
        self::assertSame('other', $this->dto(mimeType: 'application/x-unknown', extension: 'bin')->category());
    }

    public function test_size_formatted_produces_human_readable_units(): void
    {
        self::assertSame('0 B', StoredFileDTO::sizeFormatted(0));
        self::assertSame('512 B', StoredFileDTO::sizeFormatted(512));
        self::assertSame('2.0 KB', StoredFileDTO::sizeFormatted(2048));
        self::assertSame('1.0 MB', StoredFileDTO::sizeFormatted(1024 * 1024));
    }

    public function test_request_deletion_moves_file_to_trash_and_restore_reverts_it(): void
    {
        $repo = new InMemoryStorageRepository();
        $repo->seed($this->dto(uuid: 'a1111111-1111-4111-8111-111111111111'));
        $service = $this->service($repo);

        $trashed = $service->requestDeletion('a1111111-1111-4111-8111-111111111111', actorId: 7);
        self::assertSame('pending_delete', $trashed->status);

        $restored = $service->restore('a1111111-1111-4111-8111-111111111111', actorId: 7);
        self::assertSame('active', $restored->status);
    }

    public function test_permanent_delete_is_blocked_when_file_is_still_referenced(): void
    {
        $repo = new InMemoryStorageRepository();
        $repo->seed($this->dto(uuid: 'b2222222-2222-4222-8222-222222222222'));
        $repo->usageCounts['b2222222-2222-4222-8222-222222222222'] = 2;
        $service = $this->service($repo);

        $this->expectException(ConflictException::class);
        $service->delete('b2222222-2222-4222-8222-222222222222');
    }

    public function test_find_by_uuid_throws_not_found_for_unknown_file(): void
    {
        $service = $this->service(new InMemoryStorageRepository());

        $this->expectException(NotFoundException::class);
        $service->findByUuid('c3333333-3333-4333-8333-333333333333');
    }

    private function dto(
        string $uuid = 'd4444444-4444-4444-8444-444444444444',
        ?string $mimeType = 'application/octet-stream',
        ?string $extension = 'bin',
    ): StoredFileDTO {
        return new StoredFileDTO(
            id: 1,
            uuid: $uuid,
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'storage/general/2026/07/' . $uuid . '.' . $extension,
            ownerType: 'storage',
            ownerUuid: $uuid,
            fieldName: 'file',
            originalName: 'sample.' . $extension,
            mimeType: $mimeType,
            extension: $extension,
            sizeBytes: 1024,
        );
    }

    private function service(IStorageRepository $repo): StorageService
    {
        return new StorageService($repo, new StorageSettings(new SettingsService()), new NullAuditService());
    }
}

final class InMemoryStorageRepository implements IStorageRepository
{
    /** @var array<string, StoredFileDTO> */
    public array $files = [];

    /** @var array<string, int> */
    public array $usageCounts = [];

    public function seed(StoredFileDTO $file): void
    {
        $this->files[$file->uuid] = $file;
    }

    public function create(array $data): StoredFileDTO
    {
        $dto = new StoredFileDTO(
            id: count($this->files) + 1,
            uuid: (string) $data['uuid'],
            disk: (string) $data['disk'],
            visibility: (string) $data['visibility'],
            status: (string) ($data['status'] ?? 'active'),
            path: (string) $data['path'],
            ownerType: (string) $data['owner_type'],
            ownerUuid: (string) $data['owner_uuid'],
            fieldName: (string) $data['field_name'],
            originalName: (string) $data['original_name'],
            mimeType: $data['mime_type'] ?? null,
            extension: $data['extension'] ?? null,
            sizeBytes: (int) ($data['size_bytes'] ?? 0),
            width: $data['width'] ?? null,
            height: $data['height'] ?? null,
            checksumSha256: $data['checksum_sha256'] ?? null,
            uploadedBy: $data['uploaded_by'] ?? null,
        );
        $this->files[$dto->uuid] = $dto;

        return $dto;
    }

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): ?StoredFileDTO
    {
        $file = $this->files[$uuid] ?? null;
        if ($file === null) {
            return null;
        }
        if (!$includePendingDeletion && $file->status === 'pending_delete') {
            return null;
        }

        return $file;
    }

    public function list(array $filters = []): array
    {
        return array_values($this->files);
    }

    public function count(array $filters = []): int
    {
        return count($this->files);
    }

    public function summary(array $filters = []): array
    {
        return ['totalFiles' => count($this->files), 'totalBytes' => 0, 'byCategory' => []];
    }

    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO
    {
        $file = $this->files[$uuid] ?? throw new NotFoundException('Upload ' . $uuid);
        $updated = new StoredFileDTO(
            id: $file->id,
            uuid: $file->uuid,
            disk: $file->disk,
            visibility: $file->visibility,
            status: 'pending_delete',
            path: $file->path,
            ownerType: $file->ownerType,
            ownerUuid: $file->ownerUuid,
            fieldName: $file->fieldName,
            originalName: $file->originalName,
            mimeType: $file->mimeType,
            extension: $file->extension,
            sizeBytes: $file->sizeBytes,
            width: $file->width,
            height: $file->height,
            checksumSha256: $file->checksumSha256,
            uploadedBy: $file->uploadedBy,
            deletionRequestedBy: $actorId,
        );
        $this->files[$uuid] = $updated;

        return $updated;
    }

    public function restore(string $uuid): StoredFileDTO
    {
        $file = $this->files[$uuid] ?? throw new NotFoundException('Upload ' . $uuid);
        $updated = new StoredFileDTO(
            id: $file->id,
            uuid: $file->uuid,
            disk: $file->disk,
            visibility: $file->visibility,
            status: 'active',
            path: $file->path,
            ownerType: $file->ownerType,
            ownerUuid: $file->ownerUuid,
            fieldName: $file->fieldName,
            originalName: $file->originalName,
            mimeType: $file->mimeType,
            extension: $file->extension,
            sizeBytes: $file->sizeBytes,
            width: $file->width,
            height: $file->height,
            checksumSha256: $file->checksumSha256,
            uploadedBy: $file->uploadedBy,
        );
        $this->files[$uuid] = $updated;

        return $updated;
    }

    public function delete(string $uuid): void
    {
        if (!isset($this->files[$uuid])) {
            throw new NotFoundException('Upload ' . $uuid);
        }
        unset($this->files[$uuid]);
    }

    public function usageCount(string $uuid): int
    {
        return $this->usageCounts[$uuid] ?? 0;
    }
}

final class NullAuditService implements IAuditService
{
    public function record(
        string $action,
        string $entityType,
        string $entityId,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $actorId = null,
        ?string $actorType = null,
        ?string $idempotencyKey = null,
        ?array $metadata = [],
    ): void {
        // No-op for tests.
    }
}
