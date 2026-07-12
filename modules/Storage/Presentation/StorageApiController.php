<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Presentation;

use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Authorization\StoragePermissions;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Settings\StorageSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class StorageApiController
{
    private const INLINE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/svg+xml', 'image/webp', 'image/gif', 'text/plain'];
    private const DEFAULT_PER_PAGE = 24;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly IStorageService $storage,
        private readonly ISessionService $session,
        private readonly StorageSettings $storageSettings,
        private readonly SettingsService $settings,
        private readonly IAuditService $audit,
    ) {}

    public function list(Request $request): Response
    {
        $ctx = $this->requireContext();
        $filters = $this->filters($request);
        $files = $this->storage->list($filters);
        $total = $this->storage->count($filters);
        $perPage = (int) $filters['limit'];
        $page = (int) floor(((int) $filters['offset']) / max(1, $perPage)) + 1;

        return Response::success([
            'data' => array_map(fn(StoredFileDTO $file): array => $this->present($file, $ctx), $files),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => (int) max(1, ceil($total / max(1, $perPage))),
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
            ],
        ], 'Files retrieved successfully');
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requireContext();
        $file = $this->storage->findByUuid((string) ($request->routeParam('uuid') ?? ''));

        return Response::success($this->present($file, $ctx, includeDetails: true), 'File retrieved successfully');
    }

    public function summary(Request $request): Response
    {
        $filters = $this->filters($request);
        unset($filters['limit'], $filters['offset']);
        $summary = $this->storage->summary($filters);

        return Response::success([
            'totalFiles' => $summary['totalFiles'],
            'totalBytes' => $summary['totalBytes'],
            'totalFormatted' => StoredFileDTO::sizeFormatted($summary['totalBytes']),
            'byCategory' => array_map(
                static fn(array $entry): array => $entry + ['bytesFormatted' => StoredFileDTO::sizeFormatted($entry['bytes'])],
                $summary['byCategory'],
            ),
            'limits' => [
                'maxUploadBytes' => $this->storageSettings->maxUploadBytes(),
                'allowedExtensions' => $this->storageSettings->allowedExtensions(),
                'allowedMimeTypes' => $this->storageSettings->allowedMimeTypes(),
            ],
        ], 'Storage summary retrieved successfully');
    }

    public function upload(Request $request): Response
    {
        $actorId = $this->actorId();
        $ctx = $this->requireContext();
        $form = array_merge($request->query, $request->body, $request->json);
        $file = $request->files['file'] ?? $this->firstFile($request->files);
        if ($file === null) {
            throw new ValidationException(['file' => 'File is required.']);
        }

        $ownerUuid = $this->nullableString($form['owner_uuid'] ?? null) ?? UuidSupport::generate();
        $stored = $this->storage->storeUploadedFile(new StoreUploadedFileRequest(
            file: $file,
            ownerType: $this->nullableString($form['owner_type'] ?? null) ?? 'storage',
            ownerUuid: $ownerUuid,
            fieldName: $this->nullableString($form['field_name'] ?? null) ?? 'file',
            visibility: $this->nullableString($form['visibility'] ?? null),
            disk: $this->nullableString($form['disk'] ?? null),
            actorId: $actorId,
        ));
        if ($stored === null) {
            throw new ValidationException(['file' => 'File is required.']);
        }

        return Response::success($this->present($stored, $ctx), 'File uploaded successfully', 201);
    }

    public function view(Request $request): Response
    {
        $actorId = $this->actorId();
        $file = $this->storage->findByUuid((string) ($request->routeParam('uuid') ?? ''));
        if (!in_array((string) $file->mimeType, self::INLINE_MIME_TYPES, true)) {
            throw new ValidationException(['file' => 'This file type cannot be viewed inline.']);
        }
        $this->auditFileAccess($actorId, 'storage.file.viewed', $file);

        return Response::html($this->storage->read($file->uuid))
            ->withHeader('Content-Type', $file->mimeType ?? 'application/octet-stream')
            ->withHeader('Content-Disposition', 'inline; filename="' . $this->headerFilename($file->originalName) . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public function download(Request $request): Response
    {
        $actorId = $this->actorId();
        $file = $this->storage->findByUuid((string) ($request->routeParam('uuid')));
        $this->auditFileAccess($actorId, 'storage.file.downloaded', $file);

        return Response::html($this->storage->read($file->uuid))
            ->withHeader('Content-Type', $file->mimeType ?? 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $this->headerFilename($file->originalName) . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public function publicView(Request $request): Response
    {
        $file = $this->publicFile((string) ($request->routeParam('uuid')));
        if (!in_array((string) $file->mimeType, self::INLINE_MIME_TYPES, true)) {
            throw new ValidationException(['file' => 'This file type cannot be viewed inline.']);
        }

        return Response::html($this->storage->read($file->uuid))
            ->withHeader('Content-Type', $file->mimeType ?? 'application/octet-stream')
            ->withHeader('Content-Disposition', 'inline; filename="' . $this->headerFilename($file->originalName) . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public function publicDownload(Request $request): Response
    {
        $file = $this->publicFile((string) ($request->routeParam('uuid')));

        return Response::html($this->storage->read($file->uuid))
            ->withHeader('Content-Type', $file->mimeType ?? 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $this->headerFilename($file->originalName) . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public function usage(Request $request): Response
    {
        $uuid = (string) ($request->routeParam('uuid') ?? '');
        $file = $this->storage->findByUuid($uuid, true);
        $count = $this->storage->usageCount($uuid);

        return Response::success([
            'uuid' => $file->uuid,
            'usageCount' => $count,
            'canPermanentlyDelete' => $count === 0,
        ], 'Usage retrieved successfully');
    }

    /** Move a file to trash (soft delete). Original endpoint behaviour, unchanged. */
    public function delete(Request $request): Response
    {
        $actorId = $this->actorId();
        $file = $this->storage->requestDeletion((string) ($request->routeParam('uuid')), $actorId);

        return Response::success(['uuid' => $file->uuid, 'status' => $file->status], 'File moved to trash.');
    }

    public function restore(Request $request): Response
    {
        $actorId = $this->actorId();
        $file = $this->storage->restore((string) ($request->routeParam('uuid')), $actorId);

        return Response::success(['uuid' => $file->uuid, 'status' => $file->status], 'File restored.');
    }

    /** Permanently delete a file. Blocked if the file is still referenced by other modules. */
    public function destroy(Request $request): Response
    {
        $actorId = $this->actorId();
        $file = $this->storage->delete((string) ($request->routeParam('uuid')), $actorId);

        return Response::success(['uuid' => $file->uuid], 'File permanently deleted.');
    }

    public function settings(Request $request): Response
    {
        $this->requirePrivilege(StoragePermissions::SETTINGS_MANAGE);

        return Response::success([
            'values' => $this->settings->getAllForModule('storage'),
            'definitions' => array_map(static fn($definition): array => [
                'key' => $definition->key,
                'type' => $definition->type->value,
                'default' => $definition->default,
                'label' => $definition->label,
                'description' => $definition->description,
                'editable' => $definition->editable,
                'sensitive' => $definition->sensitive,
                'restartRequired' => $definition->restartRequired,
            ], $this->settings->getRegistry()->getForModule('storage')),
            'derived' => [
                'defaultDisk' => $this->storageSettings->defaultDisk(),
                'defaultVisibility' => $this->storageSettings->defaultVisibility(),
                'localPrivateRoot' => $this->storageSettings->localPrivateRoot(),
                'maxUploadBytes' => $this->storageSettings->maxUploadBytes(),
                'allowedExtensions' => $this->storageSettings->allowedExtensions(),
                'allowedMimeTypes' => $this->storageSettings->allowedMimeTypes(),
            ],
        ]);
    }

    public function updateSettings(Request $request): Response
    {
        $actorId = $this->requirePrivilege(StoragePermissions::SETTINGS_MANAGE);
        $payload = array_replace($request->query, $request->body, $request->json);
        $values = $payload['values'] ?? $payload;
        $allowed = array_flip([
            'default_disk',
            'default_visibility',
            'local_private_root',
            'max_upload_bytes',
            'allowed_extensions',
            'allowed_mime_types',
        ]);

        $this->settings->setMany(
            'storage',
            is_array($values) ? array_intersect_key($values, $allowed) : [],
            (string) $actorId,
        );

        return $this->settings($request);
    }

    /**
     * Map the internal DTO to the stable, permission-aware API/UI shape.
     * The backend stays authoritative for capability flags — the frontend
     * must never re-derive canView/canDownload/canDelete itself.
     *
     * @return array<string, mixed>
     */
    private function present(StoredFileDTO $file, UserContext $ctx, bool $includeDetails = false): array
    {
        $isPublic = $file->visibility === 'public';
        $canView = $ctx->hasPrivilege(StoragePermissions::FILE_VIEW) && in_array((string) $file->mimeType, self::INLINE_MIME_TYPES, true);
        $canDownload = $ctx->hasPrivilege(StoragePermissions::FILE_DOWNLOAD);
        $canDelete = $ctx->hasPrivilege(StoragePermissions::FILE_DELETE);

        $data = [
            'uuid' => $file->uuid,
            'originalName' => $file->originalName,
            'displayName' => $file->originalName,
            'mimeType' => $file->mimeType,
            'category' => $file->category(),
            'extension' => $file->extension,
            'sizeBytes' => $file->sizeBytes,
            'sizeFormatted' => StoredFileDTO::sizeFormatted($file->sizeBytes),
            'width' => $file->width,
            'height' => $file->height,
            'visibility' => $file->visibility,
            'status' => $file->status,
            'ownerType' => $file->ownerType,
            'ownerUuid' => $file->ownerUuid,
            'uploadedBy' => $file->uploadedBy !== null ? [
                'id' => $file->uploadedBy,
                'name' => $file->uploadedByName ?? 'Unknown',
            ] : null,
            'uploadedAt' => $file->createdAt,
            'updatedAt' => $file->updatedAt,
            'deletionRequestedAt' => $file->deletionRequestedAt,
            'previewUrl' => '/api/v1/storage/files/' . $file->uuid . '/view',
            'downloadUrl' => '/api/v1/storage/files/' . $file->uuid . '/download',
            'publicUrl' => $isPublic ? '/api/v1/files/' . $file->uuid . '/view' : null,
            'canView' => $canView,
            'canDownload' => $canDownload,
            'canDelete' => $canDelete,
            'canCopyPublicLink' => $isPublic,
        ];

        if ($includeDetails) {
            $data['checksumSha256'] = $file->checksumSha256;
            $data['disk'] = $file->disk;
            $data['fieldName'] = $file->fieldName;
            $data['usageCount'] = $this->storage->usageCount($file->uuid);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $perPage = (int) ($request->query('per_page') ?? $request->query('limit') ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $page = (int) ($request->query('page') ?? 0);
        $offset = $page > 0 ? ($page - 1) * $perPage : max(0, (int) ($request->query('offset') ?? 0));

        return [
            'owner_type' => $this->nullableString($request->query('owner_type')),
            'owner_uuid' => $this->nullableString($request->query('owner_uuid')),
            'visibility' => $this->nullableString($request->query('visibility')),
            'disk' => $this->nullableString($request->query('disk')),
            'search' => $this->nullableString($request->query('search')),
            'category' => $this->nullableString($request->query('type') ?? $request->query('category')),
            'uploaded_by' => $this->nullableString($request->query('uploaded_by')),
            'date_from' => $this->nullableString($request->query('date_from')),
            'date_to' => $this->nullableString($request->query('date_to')),
            'sort' => $this->nullableString($request->query('sort')) ?? 'date',
            'direction' => $this->nullableString($request->query('direction')) ?? 'desc',
            'status' => $this->nullableString($request->query('status')),
            'include_pending_deletion' => filter_var($request->query('include_pending_deletion'), FILTER_VALIDATE_BOOLEAN),
            'limit' => $perPage,
            'offset' => $offset,
        ];
    }

    /** @param array<string, mixed> $files */
    private function firstFile(array $files): ?array
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                return $file;
            }
        }

        return null;
    }

    private function actorId(): int
    {
        return (int) $this->requireContext()->userId;
    }

    private function publicFile(string $uuid): StoredFileDTO
    {
        $file = $this->storage->findByUuid($uuid);
        if ($file->visibility !== 'public') {
            throw new NotFoundException('Upload ' . $uuid);
        }

        return $file;
    }

    private function auditFileAccess(int $actorId, string $action, StoredFileDTO $file): void
    {
        $this->audit->record(
            actorId: (string) $actorId,
            action: $action,
            entityType: 'StoredFile',
            entityId: $file->uuid,
            metadata: ['module' => 'Storage'],
            afterState: [
                'uuid' => $file->uuid,
                'owner_type' => $file->ownerType,
                'owner_uuid' => $file->ownerUuid,
                'field_name' => $file->fieldName,
                'visibility' => $file->visibility,
                'mime_type' => $file->mimeType,
                'size_bytes' => $file->sizeBytes,
            ],
        );
    }

    private function requirePrivilege(string $permission): int
    {
        $ctx = $this->requireContext();
        if (!$ctx->hasPrivilege($permission)) {
            throw new ForbiddenException("Missing required privilege: {$permission}");
        }

        return (int) $ctx->userId;
    }

    private function requireContext(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }

    private function headerFilename(string $name): string
    {
        return str_replace(['"', "\r", "\n"], '', $name);
    }
}
