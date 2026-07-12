<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Presentation;

use WorkEddy\Modules\Content\Application\Services\ContentQueryService;
use WorkEddy\Modules\Content\Application\Services\ContentWorkflowService;
use WorkEddy\Modules\Content\Authorization\ContentPermissions;
use WorkEddy\Modules\Content\Domain\ContentMedia;
use WorkEddy\Modules\Content\Domain\Contracts\IContentMediaRepository;
use WorkEddy\Modules\Content\Support\ContentSnapshotNormalizer;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class ContentApiController
{
    public function __construct(
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly ContentWorkflowService $workflow,
        private readonly ContentQueryService $queries,
        private readonly IContentMediaRepository $media,
        private readonly IStorageService $storage,
        private readonly IAuditService $audit,
    ) {}

    public function listPages(Request $request): Response
    {
        unset($request);
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);

        return Response::success(['pages' => $this->queries->listPages()], 'Content pages loaded.');
    }

    public function createPage(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_CREATE);

        $pageKey = trim((string) $request->input('pageKey', ''));
        $routePath = trim((string) $request->input('routePath', ''));
        $contentType = trim((string) $request->input('contentType', 'structured-page'));
        $templateKey = trim((string) $request->input('templateKey', 'internal_default'));
        $audience = trim((string) $request->input('audience', 'internal'));
        $title = trim((string) $request->input('title', 'Untitled content page'));
        $snapshot = ContentSnapshotNormalizer::normalize(
            is_array($request->input('snapshot')) ? $request->input('snapshot') : ['sections' => [], 'references' => []]
        );
        $this->requireReferenceManagePrivilege($ctx, [], $snapshot['references'] ?? []);

        if ($pageKey === '' || $routePath === '') {
            throw new ValidationException([
                'pageKey' => $pageKey === '' ? 'Page key is required.' : null,
                'routePath' => $routePath === '' ? 'Route path is required.' : null,
            ]);
        }

        $page = $this->workflow->createPage(
            pageKey: $pageKey,
            routePath: $routePath,
            contentType: $contentType,
            templateKey: $templateKey,
            audience: $audience,
            actorId: (int) $ctx->userId,
            title: $title,
            snapshot: $snapshot,
            changeSummary: $this->nullableString($request->input('changeSummary')) ?? 'Page created',
            seoTitle: $this->nullableString($request->input('seoTitle')),
            seoDescription: $this->nullableString($request->input('seoDescription')),
        );

        return Response::success([
            'pageUuid' => $page->uuid,
            'pageKey' => $page->pageKey,
            'routePath' => $page->routePath,
            'status' => $page->status,
        ], 'Content page created.');
    }

    public function getPage(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $pageUuid = (string) $request->routeParam('pageUuid');

        return Response::success([
            'page' => $this->requirePageSummary($pageUuid),
        ], 'Content page loaded.');
    }

    public function archivePage(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_ARCHIVE);
        $page = $this->requirePageSummary((string) $request->routeParam('pageUuid'));
        $archived = $this->workflow->archivePage((string) $page['pageKey'], (int) $ctx->userId);

        return Response::success([
            'pageUuid' => $archived->uuid,
            'pageKey' => $archived->pageKey,
            'status' => $archived->status,
        ], 'Content page archived.');
    }

    public function restorePage(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_ARCHIVE);
        $page = $this->requirePageSummary((string) $request->routeParam('pageUuid'));
        $restored = $this->workflow->restorePage((string) $page['pageKey'], (int) $ctx->userId);

        return Response::success([
            'pageUuid' => $restored->uuid,
            'pageKey' => $restored->pageKey,
            'status' => $restored->status,
        ], 'Content page restored.');
    }

    public function getDraft(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_UPDATE);
        $pageUuid = (string) $request->routeParam('pageUuid');

        return Response::success([
            'draft' => $this->queries->findDraftByUuid($pageUuid),
        ], 'Draft loaded.');
    }

    public function previewDraft(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PREVIEW);
        $pageUuid = (string) $request->routeParam('pageUuid');

        return Response::success([
            'draft' => $this->queries->findDraftByUuid($pageUuid),
        ], 'Draft preview loaded.');
    }

    public function saveDraft(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_UPDATE);
        $page = $this->requirePageSummary((string) $request->routeParam('pageUuid'));
        $existingDraft = $this->queries->findDraftByUuid((string) $page['pageUuid']);
        $snapshot = ContentSnapshotNormalizer::normalize(
            is_array($request->input('snapshot')) ? $request->input('snapshot') : ['sections' => [], 'references' => []]
        );
        $this->requireReferenceManagePrivilege(
            $ctx,
            is_array($existingDraft?->snapshot['references'] ?? null) ? $existingDraft->snapshot['references'] : [],
            $snapshot['references'] ?? [],
        );

        $revision = $this->workflow->saveDraft(
            pageKey: (string) $page['pageKey'],
            title: trim((string) $request->input('title', (string) ($page['title'] ?? 'Untitled content page'))),
            seoTitle: $this->nullableString($request->input('seoTitle')),
            seoDescription: $this->nullableString($request->input('seoDescription')),
            snapshot: $snapshot,
            expectedLockVersion: (int) $request->input('expectedLockVersion', 0),
            actorId: (int) $ctx->userId,
            changeSummary: $this->nullableString($request->input('changeSummary')),
        );

        return Response::success([
            'pageUuid' => (string) $page['pageUuid'],
            'revisionUuid' => $revision->uuid,
            'revisionStatus' => $revision->revisionStatus,
        ], 'Draft saved.');
    }

    public function publish(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_PUBLISH);
        $page = $this->requirePageSummary((string) $request->routeParam('pageUuid'));
        $published = $this->workflow->publishDraft((string) $page['pageKey'], (int) $ctx->userId, $this->nullableString($request->input('changeSummary')));

        return Response::success([
            'pageUuid' => (string) $page['pageUuid'],
            'revisionUuid' => $published->revisionUuid,
            'publishedAt' => $published->publishedAt->format(DATE_ATOM),
        ], 'Content published.');
    }

    public function listRevisions(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $pageUuid = (string) $request->routeParam('pageUuid');

        return Response::success([
            'history' => $this->queries->listRevisionHistoryByPageUuid($pageUuid),
        ], 'Revision history loaded.');
    }

    public function getRevision(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_READ);
        $pageUuid = (string) $request->routeParam('pageUuid');
        $revisionUuid = (string) $request->routeParam('revisionUuid');

        return Response::success([
            'revision' => $this->queries->findRevisionForPage($pageUuid, $revisionUuid),
        ], 'Revision loaded.');
    }

    public function restoreRevision(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::PAGES_RESTORE);
        $page = $this->requirePageSummary((string) $request->routeParam('pageUuid'));
        $revisionUuid = (string) $request->routeParam('revisionUuid');
        $revision = $this->workflow->restoreRevision((string) $page['pageKey'], $revisionUuid, (int) $ctx->userId, $this->nullableString($request->input('changeSummary')));

        return Response::success([
            'pageUuid' => (string) $page['pageUuid'],
            'revisionUuid' => $revision->uuid,
            'revisionStatus' => $revision->revisionStatus,
        ], 'Revision restored into a new draft.');
    }

    public function listMedia(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::MEDIA_READ);
        $items = array_map(static fn(ContentMedia $media): array => [
            'uuid' => $media->uuid,
            'storageFileUuid' => $media->storageFileUuid,
            'originalName' => $media->originalName,
            'mimeType' => $media->mimeType,
            'width' => $media->width,
            'height' => $media->height,
            'defaultAltText' => $media->defaultAltText,
            'defaultCaption' => $media->defaultCaption,
            'status' => $media->status,
        ], $this->media->listSelectable());

        return Response::success(['media' => $items], 'Media library loaded.');
    }

    public function getMedia(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::MEDIA_READ);
        $mediaUuid = (string) $request->routeParam('mediaUuid');
        $media = $this->media->findByUuid($mediaUuid) ?? throw new \RuntimeException('Content media "' . $mediaUuid . '" was not found.');

        return Response::success([
            'media' => [
                'uuid' => $media->uuid,
                'storageFileUuid' => $media->storageFileUuid,
                'originalName' => $media->originalName,
                'mimeType' => $media->mimeType,
                'width' => $media->width,
                'height' => $media->height,
                'defaultAltText' => $media->defaultAltText,
                'defaultCaption' => $media->defaultCaption,
                'status' => $media->status,
            ],
        ], 'Media loaded.');
    }

    public function uploadMedia(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::MEDIA_UPLOAD);
        $file = $request->files['file'] ?? null;
        if (!is_array($file)) {
            throw new ValidationException(['file' => 'An image file is required.']);
        }

        $validation = $this->validateUploadedImage($file);
        if ($validation !== []) {
            throw new ValidationException($validation);
        }

        $stored = $this->storage->storeUploadedFile(new StoreUploadedFileRequest(
            file: $file,
            ownerType: 'content_media',
            ownerUuid: UuidSupport::generate(),
            fieldName: 'file',
            visibility: 'private',
            actorId: (int) $ctx->userId,
        ));

        if ($stored === null) {
            throw new \RuntimeException('Unable to store content media file.');
        }

        [$width, $height] = array_map(static fn(mixed $value): ?int => is_numeric((string) $value) ? (int) $value : null, getimagesize($file['tmp_name']) ?: [null, null]);
        $record = $this->media->create(new ContentMedia(
            id: null,
            uuid: UuidSupport::generate(),
            storageFileUuid: $stored->uuid,
            originalName: (string) $file['name'],
            mimeType: (string) mime_content_type($file['tmp_name']),
            extension: pathinfo((string) $file['name'], PATHINFO_EXTENSION) ?: null,
            sizeBytes: (int) ($file['size'] ?? 0),
            width: $width,
            height: $height,
            defaultAltText: $this->nullableString($request->input('defaultAltText')),
            defaultCaption: $this->nullableString($request->input('defaultCaption')),
            status: 'active',
            uploadedBy: (int) $ctx->userId,
            createdAt: gmdate('Y-m-d H:i:s'),
            updatedAt: gmdate('Y-m-d H:i:s'),
            archivedAt: null,
        ));
        $this->audit->record('content.media.uploaded', 'content_media', $record->uuid, null, ['storageFileUuid' => $record->storageFileUuid], (string) $ctx->userId, 'user');

        return Response::success([
            'mediaUuid' => $record->uuid,
            'storageFileUuid' => $record->storageFileUuid,
        ], 'Media uploaded.');
    }

    public function updateMedia(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::MEDIA_UPDATE);
        $mediaUuid = (string) $request->routeParam('mediaUuid');
        $media = $this->workflow->updateMediaMetadata(
            $mediaUuid,
            $this->nullableString($request->input('defaultAltText')),
            $this->nullableString($request->input('defaultCaption')),
            (int) $ctx->userId,
        );

        return Response::success([
            'mediaUuid' => $media->uuid,
            'defaultAltText' => $media->defaultAltText,
            'defaultCaption' => $media->defaultCaption,
        ], 'Media metadata updated.');
    }

    public function archiveMedia(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ContentPermissions::MEDIA_ARCHIVE);
        $mediaUuid = (string) $request->routeParam('mediaUuid');
        $media = $this->workflow->archiveMedia($mediaUuid, (int) $ctx->userId);

        return Response::success([
            'mediaUuid' => $media->uuid,
            'status' => $media->status,
        ], 'Media archived.');
    }

    /** @param array<string, mixed> $file */
    private function validateUploadedImage(array $file): array
    {
        $errors = [];
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmpName === '' || !is_file($tmpName)) {
            $errors['file'] = 'Uploaded file is missing.';
            return $errors;
        }

        $mime = mime_content_type($tmpName) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $errors['file'] = 'Only JPEG, PNG, and WebP images are allowed.';
        }
        if ($size > 5 * 1024 * 1024) {
            $errors['size'] = 'Image exceeds the 5 MB size limit.';
        }

        $dimensions = getimagesize($tmpName);
        if ($dimensions === false) {
            $errors['image'] = 'Uploaded file is not a valid image.';
            return $errors;
        }

        [$width, $height] = $dimensions;
        if ($width > 5000 || $height > 5000) {
            $errors['dimensions'] = 'Image exceeds the maximum 5000x5000 dimensions.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePageSummary(string $pageUuid): array
    {
        return $this->queries->findPageSummaryByUuid($pageUuid)
            ?? throw new \RuntimeException('Content page "' . $pageUuid . '" was not found.');
    }

    /**
     * @param list<mixed> $before
     * @param list<mixed> $after
     */
    private function requireReferenceManagePrivilege(UserContext $ctx, array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        $this->permissions->requirePrivilege($ctx, ContentPermissions::REFERENCES_MANAGE);
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $ctx;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
