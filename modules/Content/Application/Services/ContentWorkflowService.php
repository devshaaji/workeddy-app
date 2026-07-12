<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Application\Services;

use WorkEddy\Modules\Content\Application\DTOs\ContentPreviewPage;
use WorkEddy\Modules\Content\Domain\ContentPage;
use WorkEddy\Modules\Content\Domain\ContentPageRevision;
use WorkEddy\Modules\Content\Domain\Contracts\IContentMediaRepository;
use WorkEddy\Modules\Content\Domain\Contracts\IContentPageRepository;
use WorkEddy\Modules\Content\Support\ContentPageSchemaRegistry;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class ContentWorkflowService
{
    public function __construct(
        private readonly IContentPageRepository $pages,
        private readonly ContentPageSchemaRegistry $schemas,
        private readonly IAuditService $audit,
        private readonly ?IContentMediaRepository $media = null,
    ) {}

    /**
     * @param array<string, mixed> $snapshot
     */
    public function createPage(
        string $pageKey,
        string $routePath,
        string $contentType,
        string $templateKey,
        string $audience,
        int $actorId,
        string $title,
        array $snapshot,
        ?string $changeSummary = null,
        ?string $seoTitle = null,
        ?string $seoDescription = null,
    ): ContentPage {
        $this->validateSnapshot($pageKey, 'draft', $snapshot);

        $now = gmdate('Y-m-d H:i:s');
        $draft = new ContentPageRevision(
            id: null,
            uuid: UuidSupport::generate(),
            pageId: null,
            versionNumber: 1,
            revisionStatus: 'draft',
            title: $title,
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
            changeSummary: $changeSummary,
            contentSnapshot: $snapshot,
            createdBy: $actorId,
            createdAt: $now,
            updatedBy: $actorId,
            updatedAt: $now,
            publishedBy: null,
            publishedAt: null,
        );
        $page = new ContentPage(
            id: null,
            uuid: UuidSupport::generate(),
            pageKey: $pageKey,
            routePath: $routePath,
            contentType: $contentType,
            templateKey: $templateKey,
            audience: $audience,
            status: 'draft',
            publishedRevisionId: null,
            draftRevisionId: null,
            lockVersion: 1,
            createdBy: $actorId,
            createdAt: $now,
            updatedAt: $now,
            archivedAt: null,
            archivedBy: null,
        );

        $persisted = $this->pages->createPage($page, $draft);
        $this->audit->record('content.page.created', 'content_page', $persisted->uuid, null, ['pageKey' => $pageKey], (string) $actorId, 'user');

        return $persisted;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function saveDraft(
        string $pageKey,
        string $title,
        ?string $seoTitle,
        ?string $seoDescription,
        array $snapshot,
        int $expectedLockVersion,
        int $actorId,
        ?string $changeSummary = null,
    ): ContentPageRevision {
        $page = $this->requirePage($pageKey);
        $this->assertLockVersion($page->lockVersion, $expectedLockVersion);
        if ($page->draftRevisionId === null) {
            throw new ConflictException('No active draft exists for page ' . $pageKey . '.');
        }

        $this->validateSnapshot($pageKey, 'draft', $snapshot);
        $draft = $this->requireRevisionById($page->draftRevisionId);
        $updatedAt = gmdate('Y-m-d H:i:s');
        $updatedDraft = $draft->updateDraft($title, $seoTitle, $seoDescription, $snapshot, $actorId, $updatedAt, $changeSummary);
        $this->pages->updateRevision($updatedDraft);
        $this->pages->updatePage($page->withDraftRevision($page->draftRevisionId, $updatedAt, 'draft'));
        $this->audit->record('content.draft.saved', 'content_page', $page->uuid, null, ['revisionUuid' => $updatedDraft->uuid], (string) $actorId, 'user');
        if (($draft->contentSnapshot['references'] ?? []) !== ($snapshot['references'] ?? [])) {
            $this->audit->record('content.references.changed', 'content_page', $page->uuid, null, ['revisionUuid' => $updatedDraft->uuid], (string) $actorId, 'user');
        }

        return $updatedDraft;
    }

    public function publishDraft(string $pageKey, int $actorId, ?string $changeSummary = null): \WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage
    {
        $page = $this->requirePage($pageKey);
        if ($page->archivedAt !== null || $page->status === 'archived') {
            throw new ConflictException('Archived pages cannot be published.');
        }
        if ($page->draftRevisionId === null) {
            throw new ConflictException('No active draft exists for page ' . $pageKey . '.');
        }

        $draft = $this->requireRevisionById($page->draftRevisionId);
        $this->validateSnapshot($pageKey, 'published', $draft->contentSnapshot);
        $publishedAt = gmdate('Y-m-d H:i:s');
        $published = $draft->publish($actorId, $publishedAt, $changeSummary);
        $this->pages->updateRevision($published);
        $this->pages->updatePage($page->withPublishedRevision($published->id ?? 0, $publishedAt));
        $this->audit->record('content.revision.published', 'content_page', $page->uuid, null, ['revisionUuid' => $published->uuid], (string) $actorId, 'user');

        $reader = new ContentQueryService($this->pages);

        return $reader->findPublishedByKey($pageKey) ?? throw new \RuntimeException('Published page could not be reloaded.');
    }

    public function beginDraftFromPublished(string $pageKey, int $actorId, ?string $changeSummary = null): ContentPageRevision
    {
        $page = $this->requirePage($pageKey);
        if ($page->publishedRevisionId === null) {
            throw new ConflictException('No published revision exists for page ' . $pageKey . '.');
        }

        if ($page->draftRevisionId !== null) {
            return $this->requireRevisionById($page->draftRevisionId);
        }

        $published = $this->requireRevisionById($page->publishedRevisionId);
        $draft = $this->pages->createRevision(new ContentPageRevision(
            id: null,
            uuid: UuidSupport::generate(),
            pageId: $page->id,
            versionNumber: $this->pages->nextVersionNumber($page->id ?? 0),
            revisionStatus: 'draft',
            title: $published->title,
            seoTitle: $published->seoTitle,
            seoDescription: $published->seoDescription,
            changeSummary: $changeSummary,
            contentSnapshot: $published->contentSnapshot,
            createdBy: $actorId,
            createdAt: gmdate('Y-m-d H:i:s'),
            updatedBy: $actorId,
            updatedAt: gmdate('Y-m-d H:i:s'),
            publishedBy: null,
            publishedAt: null,
        ));

        $this->pages->updatePage($page->withDraftRevision($draft->id, gmdate('Y-m-d H:i:s'), 'draft'));

        return $draft;
    }

    public function restoreRevision(string $pageKey, string $revisionUuid, int $actorId, ?string $changeSummary = null): ContentPageRevision
    {
        $page = $this->requirePage($pageKey);
        $source = $this->requireRevisionByUuid($revisionUuid);
        if ($page->draftRevisionId !== null) {
            $currentDraft = $this->requireRevisionById($page->draftRevisionId);
            $this->pages->updateRevision($currentDraft->supersede());
        }

        $draft = $this->pages->createRevision(new ContentPageRevision(
            id: null,
            uuid: UuidSupport::generate(),
            pageId: $page->id,
            versionNumber: $this->pages->nextVersionNumber($page->id ?? 0),
            revisionStatus: 'draft',
            title: $source->title,
            seoTitle: $source->seoTitle,
            seoDescription: $source->seoDescription,
            changeSummary: $changeSummary,
            contentSnapshot: $source->contentSnapshot,
            createdBy: $actorId,
            createdAt: gmdate('Y-m-d H:i:s'),
            updatedBy: $actorId,
            updatedAt: gmdate('Y-m-d H:i:s'),
            publishedBy: null,
            publishedAt: null,
        ));

        $this->pages->updatePage($page->withDraftRevision($draft->id, gmdate('Y-m-d H:i:s'), 'draft'));
        $this->audit->record('content.revision.restored', 'content_page', $page->uuid, null, ['sourceRevisionUuid' => $revisionUuid, 'draftRevisionUuid' => $draft->uuid], (string) $actorId, 'user');

        return $draft;
    }

    public function archivePage(string $pageKey, int $actorId): ContentPage
    {
        $page = $this->requirePage($pageKey);
        $archived = $page->archive($actorId, gmdate('Y-m-d H:i:s'));
        $this->pages->updatePage($archived);
        $this->audit->record('content.page.archived', 'content_page', $page->uuid, null, ['pageKey' => $pageKey], (string) $actorId, 'user');

        return $archived;
    }

    public function restorePage(string $pageKey, int $actorId): ContentPage
    {
        $page = $this->requirePage($pageKey);
        $restored = $page->restore(gmdate('Y-m-d H:i:s'));
        $this->pages->updatePage($restored);
        $this->audit->record('content.page.restored', 'content_page', $page->uuid, null, ['pageKey' => $pageKey], (string) $actorId, 'user');

        return $restored;
    }

    public function updateMediaMetadata(string $mediaUuid, ?string $defaultAltText, ?string $defaultCaption, int $actorId): \WorkEddy\Modules\Content\Domain\ContentMedia
    {
        if ($this->media === null) {
            throw new \RuntimeException('Content media repository is not configured.');
        }

        $media = $this->media->findByUuid($mediaUuid)
            ?? throw new \RuntimeException('Content media "' . $mediaUuid . '" was not found.');
        $updated = new \WorkEddy\Modules\Content\Domain\ContentMedia(
            id: $media->id,
            uuid: $media->uuid,
            storageFileUuid: $media->storageFileUuid,
            originalName: $media->originalName,
            mimeType: $media->mimeType,
            extension: $media->extension,
            sizeBytes: $media->sizeBytes,
            width: $media->width,
            height: $media->height,
            defaultAltText: $defaultAltText,
            defaultCaption: $defaultCaption,
            status: $media->status,
            uploadedBy: $media->uploadedBy,
            createdAt: $media->createdAt,
            updatedAt: gmdate('Y-m-d H:i:s'),
            archivedAt: $media->archivedAt,
        );
        $this->media->update($updated);
        $this->audit->record('content.media.updated', 'content_media', $mediaUuid, null, ['defaultAltText' => $defaultAltText], (string) $actorId, 'user');

        return $updated;
    }

    public function archiveMedia(string $mediaUuid, int $actorId): \WorkEddy\Modules\Content\Domain\ContentMedia
    {
        if ($this->media === null) {
            throw new \RuntimeException('Content media repository is not configured.');
        }

        $media = $this->media->findByUuid($mediaUuid)
            ?? throw new \RuntimeException('Content media "' . $mediaUuid . '" was not found.');
        $archived = new \WorkEddy\Modules\Content\Domain\ContentMedia(
            id: $media->id,
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
            status: 'archived',
            uploadedBy: $media->uploadedBy,
            createdAt: $media->createdAt,
            updatedAt: gmdate('Y-m-d H:i:s'),
            archivedAt: gmdate('Y-m-d H:i:s'),
        );
        $this->media->update($archived);
        $this->audit->record('content.media.archived', 'content_media', $mediaUuid, null, ['status' => 'archived'], (string) $actorId, 'user');

        return $archived;
    }

    public function previewDraft(string $pageKey): ?ContentPreviewPage
    {
        $page = $this->pages->findPageByKey($pageKey);
        if ($page === null || $page->draftRevisionId === null) {
            return null;
        }

        $draft = $this->pages->findRevisionById($page->draftRevisionId);
        if ($draft === null) {
            return null;
        }

        return new ContentPreviewPage($page->uuid, $page->pageKey, $page->routePath, $draft->title, $draft->uuid, $draft->revisionStatus, $draft->versionNumber, $draft->contentSnapshot, $page->lockVersion, $draft->publishedAt);
    }

    private function validateSnapshot(string $pageKey, string $targetStatus, array $snapshot): void
    {
        $schema = $this->schemas->forPage($pageKey);
        if ($schema === null) {
            return;
        }

        $validation = $schema->validate($targetStatus, $snapshot);
        if (!$validation->isValid()) {
            throw new ValidationException($validation->errors(), 'Invalid content snapshot.');
        }

        if ($this->media === null) {
            return;
        }

        $mediaErrors = [];
        foreach (($snapshot['sections'] ?? []) as $sectionIndex => $section) {
            if (!is_array($section)) {
                continue;
            }
            foreach (($section['blocks'] ?? []) as $blockIndex => $block) {
                if (!is_array($block) || ($block['type'] ?? '') !== 'image') {
                    continue;
                }
                $mediaUuid = trim((string) ($block['mediaUuid'] ?? ''));
                if ($mediaUuid === '') {
                    continue;
                }
                $media = $this->media->findByUuid($mediaUuid);
                if ($media === null) {
                    $mediaErrors['sections.' . $sectionIndex . '.blocks.' . $blockIndex . '.mediaUuid'] = 'Referenced media does not exist.';
                    continue;
                }
                if (!str_starts_with($media->mimeType, 'image/')) {
                    $mediaErrors['sections.' . $sectionIndex . '.blocks.' . $blockIndex . '.mediaUuid'] = 'Referenced media is not an image.';
                }
            }
        }

        if ($mediaErrors !== []) {
            throw new ValidationException($mediaErrors, 'Invalid media references in content snapshot.');
        }
    }

    private function requirePage(string $pageKey): ContentPage
    {
        return $this->pages->findPageByKey($pageKey)
            ?? throw new \RuntimeException('Content page "' . $pageKey . '" was not found.');
    }

    private function requireRevisionById(int $revisionId): ContentPageRevision
    {
        return $this->pages->findRevisionById($revisionId)
            ?? throw new \RuntimeException('Content revision #' . $revisionId . ' was not found.');
    }

    private function requireRevisionByUuid(string $revisionUuid): ContentPageRevision
    {
        return $this->pages->findRevisionByUuid($revisionUuid)
            ?? throw new \RuntimeException('Content revision "' . $revisionUuid . '" was not found.');
    }

    private function assertLockVersion(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new ConflictException('Draft save rejected because the page lock version is stale.');
        }
    }
}
