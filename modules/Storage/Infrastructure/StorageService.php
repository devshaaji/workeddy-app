<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Infrastructure;

use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageRepository;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Settings\StorageSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

final class StorageService implements IStorageService
{
    private ?FilesystemOperator $filesystem = null;

    public function __construct(
        private readonly IStorageRepository $files,
        private readonly StorageSettings $settings,
        private readonly IAuditService $audit,
    ) {}

    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO
    {
        if (($request->file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $this->validateUpload($request);

        $uuid = UuidSupport::generate();
        $originalName = basename((string) ($request->file['name'] ?? 'upload'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $path = $this->buildPath($uuid, $extension, $request->ownerType);
        $tmp = (string) ($request->file['tmp_name'] ?? '');
        $mimeType = $this->detectMimeType($request->file);
        [$width, $height] = $this->detectDimensions($tmp, $mimeType);
        $checksum = is_file($tmp) ? hash_file('sha256', $tmp) ?: null : null;

        $stream = fopen($tmp, 'rb');
        if ($stream === false) {
            throw new ValidationException([$request->fieldName => 'Unable to read uploaded file.']);
        }

        try {
            $this->filesystem()->writeStream($path, $stream, [
                'visibility' => $request->visibility ?? $this->settings->defaultVisibility(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        try {
            $file = $this->files->create([
                'uuid' => $uuid,
                'disk' => $request->disk ?? $this->settings->defaultDisk(),
                'visibility' => $request->visibility ?? $this->settings->defaultVisibility(),
                'status' => 'active',
                'path' => $path,
                'owner_type' => $this->normalizeSegment($request->ownerType),
                'owner_uuid' => $request->ownerUuid,
                'field_name' => $this->normalizeField($request->fieldName),
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'extension' => $extension !== '' ? $extension : null,
                'size_bytes' => (int) ($request->file['size'] ?? 0),
                'width' => $width,
                'height' => $height,
                'checksum_sha256' => $checksum,
                'uploaded_by' => $request->actorId,
            ]);
        } catch (\Throwable $e) {
            if ($this->filesystem()->fileExists($path)) {
                $this->filesystem()->delete($path);
            }
            throw $e;
        }

        $this->audit->record(
            action: 'storage.file.uploaded',
            entityType: 'StoredFile',
            entityId: $file->uuid,
            afterState: $file->toArray(),
            actorId: $request->actorId !== null ? (string) $request->actorId : null,
            metadata: ['module' => 'Storage'],
        );

        return $file;
    }

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO
    {
        UuidSupport::requireValid($uuid);

        return $this->files->findByUuid($uuid, $includePendingDeletion) ?? throw new NotFoundException('Upload ' . $uuid);
    }

    public function list(array $filters = []): array
    {
        return $this->files->list($filters);
    }

    public function count(array $filters = []): int
    {
        return $this->files->count($filters);
    }

    public function summary(array $filters = []): array
    {
        return $this->files->summary($filters);
    }

    public function read(string $uuid): string
    {
        $file = $this->findByUuid($uuid);
        if (!$this->filesystem()->fileExists($file->path)) {
            throw new NotFoundException('Stored file ' . $uuid);
        }

        return $this->filesystem()->read($file->path);
    }

    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO
    {
        UuidSupport::requireValid($uuid);

        $before = $this->files->findByUuid($uuid, true) ?? throw new NotFoundException('Upload ' .  $uuid);
        $file = $this->files->requestDeletion($uuid, $actorId);
        $this->audit->record(
            action: 'storage.file.deletion_requested',
            entityType: 'StoredFile',
            entityId: $file->uuid,
            beforeState: $before->toArray(includeInternalId: true),
            afterState: $file->toArray(includeInternalId: true),
            actorId: $actorId !== null ? (string) $actorId : null,
            metadata: ['module' => 'Storage'],
        );

        return $file;
    }

    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO
    {
        UuidSupport::requireValid($uuid);

        $before = $this->files->findByUuid($uuid, true) ?? throw new NotFoundException('Upload ' . $uuid);
        $file = $this->files->restore($uuid);
        $this->audit->record(
            action: 'storage.file.restored',
            entityType: 'StoredFile',
            entityId: $file->uuid,
            beforeState: $before->toArray(includeInternalId: true),
            afterState: $file->toArray(includeInternalId: true),
            actorId: $actorId !== null ? (string) $actorId : null,
            metadata: ['module' => 'Storage'],
        );

        return $file;
    }

    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO
    {
        $file = $this->findByUuid($uuid, true);

        $usage = $this->files->usageCount($uuid);
        if ($usage > 0) {
            throw new ConflictException(
                "This file is still referenced by {$usage} record(s) in other modules and cannot be permanently deleted. Move it to trash instead, or remove the referencing content first.",
            );
        }

        if ($this->filesystem()->fileExists($file->path)) {
            $this->filesystem()->delete($file->path);
        }
        $this->files->delete($uuid);
        $this->audit->record(
            actorId: $actorId !== null ? (string) $actorId : null,
            action: 'storage.file.deleted',
            entityType: 'StoredFile',
            entityId: $file->uuid,
            metadata: [
                'path' => $file->path,
                'module' => 'Storage',
            ],
            beforeState: $file->toArray(includeInternalId: true),
        );

        return $file;
    }

    public function usageCount(string $uuid): int
    {
        UuidSupport::requireValid($uuid);

        return $this->files->usageCount($uuid);
    }

    private function validateUpload(StoreUploadedFileRequest $request): void
    {
        $errors = [];
        $error = (int) ($request->file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            $errors[$request->fieldName] = 'Upload failed.';
        }

        UuidSupport::requireValid($request->ownerUuid, 'owner_uuid');

        $size = (int) ($request->file['size'] ?? 0);
        if ($size <= 0) {
            $errors[$request->fieldName] = 'File is required.';
        }
        $maxUploadBytes = $request->maxUploadBytes ?? $this->settings->maxUploadBytes();
        if ($size > $maxUploadBytes) {
            $errors[$request->fieldName] = 'File exceeds the upload size limit.';
        }

        $originalName = basename((string) ($request->file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = $request->allowedExtensions ?? $this->settings->allowedExtensions();
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            $errors[$request->fieldName] = 'Unsupported file extension.';
        }

        $mimeType = $this->detectMimeType($request->file);
        $allowedMimeTypes = $request->allowedMimeTypes ?? $this->settings->allowedMimeTypes();
        if ($mimeType !== null && !in_array($mimeType, $allowedMimeTypes, true)) {
            $errors[$request->fieldName] = 'Unsupported file type.';
        }

        $visibility = $request->visibility ?? $this->settings->defaultVisibility();
        if (!in_array($visibility, ['private', 'public'], true)) {
            $errors['visibility'] = 'Visibility must be private or public.';
        }

        $disk = $request->disk ?? $this->settings->defaultDisk();
        if ($disk !== 'local') {
            $errors['disk'] = 'Only local disk is supported in this build.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function filesystem(): FilesystemOperator
    {
        if ($this->filesystem !== null) {
            return $this->filesystem;
        }

        $root = APP_ROOT . '/' . trim($this->settings->localPrivateRoot(), '/\\');
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Unable to create storage root.');
        }

        return $this->filesystem = new Filesystem(new LocalFilesystemAdapter($root));
    }

    /** @param array<string, mixed> $file */
    private function detectMimeType(array $file): ?string
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_file($tmp)) {
            $detected = mime_content_type($tmp);
            if (is_string($detected) && $detected !== '') {
                $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
                if ($extension === 'csv' && in_array($detected, ['text/plain', 'text/csv', 'application/csv', 'text/x-comma-separated-values'], true)) {
                    return 'text/csv';
                }
                if ($extension === 'xlsx' && in_array($detected, ['application/zip', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
                    return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                }
                if ($extension === 'svg' && in_array($detected, ['text/plain', 'text/xml', 'application/xml', 'image/svg+xml'], true)) {
                    return 'image/svg+xml';
                }

                return $detected;
            }
        }

        $declared = trim((string) ($file['type'] ?? ''));

        return $declared !== '' ? $declared : null;
    }

    /**
     * Read pixel dimensions for raster images only. Never rasterizes/loads
     * the full file into memory beyond what getimagesize needs.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function detectDimensions(string $tmpPath, ?string $mimeType): array
    {
        if ($tmpPath === '' || !is_file($tmpPath) || $mimeType === null || !str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml') {
            return [null, null];
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    private function buildPath(string $uuid, string $extension, string $ownerType): string
    {
        $suffix = $extension !== '' ? '.' . $extension : '';

        return $this->normalizeSegment($ownerType) . '/' . date('Y/m') . '/' . $uuid . $suffix;
    }

    private function normalizeSegment(string $value): string
    {
        $normalized = strtolower(trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? '', '-'));

        return $normalized !== '' ? $normalized : 'general';
    }

    private function normalizeField(string $value): string
    {
        $normalized = strtolower(trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? '', '_'));

        return $normalized !== '' ? $normalized : 'file';
    }
}
