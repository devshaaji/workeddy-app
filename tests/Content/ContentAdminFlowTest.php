<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Content\Domain\ContentPage;
use WorkEddy\Modules\Content\Domain\ContentPageRevision;
use WorkEddy\Modules\Content\Application\Services\ContentQueryService;
use WorkEddy\Modules\Content\Application\Services\ContentWorkflowService;
use WorkEddy\Modules\Content\Domain\ContentMedia;
use WorkEddy\Modules\Content\Domain\Contracts\IContentMediaRepository;
use WorkEddy\Modules\Content\Domain\Contracts\IContentPageRepository;
use WorkEddy\Modules\Content\Support\ContentPageSchemaRegistry;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\Content\Support\MethodologyPageSchema;
use WorkEddy\Platform\Audit\IAuditService;

final class ContentAdminFlowTest extends TestCase
{
    public function test_page_listing_and_revision_history_expose_internal_methodology_page(): void
    {
        $pages = new AdminFlowPageRepository();
        $media = new AdminFlowMediaRepository();
        $workflow = new ContentWorkflowService(
            $pages,
            new ContentPageSchemaRegistry([new MethodologyPageSchema()]),
            new AdminFlowAuditService(),
            $media,
        );

        $workflow->createPage(
            MethodologyPageDefinition::PAGE_KEY,
            '/content/methodology-and-limitations',
            'managed_page',
            'internal_methodology',
            'internal',
            7,
            'Methodology and Limitations',
            MethodologyPageDefinition::seedSnapshot(),
            'Seed',
        );
        $workflow->publishDraft(MethodologyPageDefinition::PAGE_KEY, 7, 'Publish');
        $workflow->beginDraftFromPublished(MethodologyPageDefinition::PAGE_KEY, 7, 'New draft');

        $query = new ContentQueryService($pages);
        $list = $query->listPages();
        $history = $query->listRevisionHistory(MethodologyPageDefinition::PAGE_KEY);

        self::assertCount(1, $list);
        self::assertSame(MethodologyPageDefinition::PAGE_KEY, $list[0]['pageKey']);
        self::assertSame('internal', $list[0]['audience']);
        self::assertCount(2, $history);
        self::assertSame('draft', $history[0]['revisionStatus']);
        self::assertSame('published', $history[1]['revisionStatus']);
    }

    public function test_archiving_page_blocks_published_reader_and_media_archive_blocks_new_selection(): void
    {
        $pages = new AdminFlowPageRepository();
        $media = new AdminFlowMediaRepository([
            new ContentMedia(
                id: 1,
                uuid: 'media-1',
                storageFileUuid: 'storage-1',
                originalName: 'photo.jpg',
                mimeType: 'image/jpeg',
                extension: 'jpg',
                sizeBytes: 100,
                width: 320,
                height: 240,
                defaultAltText: 'default alt',
                defaultCaption: 'default caption',
                status: 'active',
                uploadedBy: 7,
                createdAt: '2026-07-11 00:00:00',
                updatedAt: '2026-07-11 00:00:00',
                archivedAt: null,
            ),
        ]);
        $workflow = new ContentWorkflowService(
            $pages,
            new ContentPageSchemaRegistry([new MethodologyPageSchema()]),
            new AdminFlowAuditService(),
            $media,
        );

        $workflow->createPage(
            MethodologyPageDefinition::PAGE_KEY,
            '/content/methodology-and-limitations',
            'managed_page',
            'internal_methodology',
            'internal',
            7,
            'Methodology and Limitations',
            MethodologyPageDefinition::seedSnapshot(),
            'Seed',
        );
        $workflow->publishDraft(MethodologyPageDefinition::PAGE_KEY, 7, 'Publish');
        $workflow->archivePage(MethodologyPageDefinition::PAGE_KEY, 7);
        $workflow->archiveMedia('media-1', 7);

        $query = new ContentQueryService($pages);
        self::assertNull($query->findPublishedByKey(MethodologyPageDefinition::PAGE_KEY));
        self::assertSame([], array_map(static fn(ContentMedia $item): string => $item->uuid, $media->listSelectable()));
    }

    public function test_media_metadata_update_changes_defaults_without_touching_revision_owned_content(): void
    {
        $pages = new AdminFlowPageRepository();
        $media = new AdminFlowMediaRepository([
            new ContentMedia(
                id: 1,
                uuid: 'media-1',
                storageFileUuid: 'storage-1',
                originalName: 'photo.jpg',
                mimeType: 'image/jpeg',
                extension: 'jpg',
                sizeBytes: 100,
                width: 320,
                height: 240,
                defaultAltText: 'old alt',
                defaultCaption: 'old caption',
                status: 'active',
                uploadedBy: 7,
                createdAt: '2026-07-11 00:00:00',
                updatedAt: '2026-07-11 00:00:00',
                archivedAt: null,
            ),
        ]);
        $audit = new AdminFlowAuditService();
        $workflow = new ContentWorkflowService(
            $pages,
            new ContentPageSchemaRegistry([new MethodologyPageSchema()]),
            $audit,
            $media,
        );

        $updated = $workflow->updateMediaMetadata('media-1', 'new alt', 'new caption', 7);

        self::assertSame('new alt', $updated->defaultAltText);
        self::assertSame('new caption', $updated->defaultCaption);
        self::assertContains('content.media.updated', array_column($audit->records, 'action'));
    }
}

final class AdminFlowPageRepository implements IContentPageRepository
{
    /** @var array<string, ContentPage> */
    private array $pages = [];
    /** @var array<int, ContentPageRevision> */
    private array $revisions = [];
    private int $nextPageId = 1;
    private int $nextRevisionId = 1;

    public function createPage(ContentPage $page, ContentPageRevision $draftRevision): ContentPage
    {
        $pageId = $this->nextPageId++;
        $revisionId = $this->nextRevisionId++;
        $persistedRevision = $draftRevision->withPersistence($revisionId, $pageId);
        $persistedPage = $page->withPersistence($pageId, $persistedRevision->id);
        $this->pages[$persistedPage->pageKey] = $persistedPage;
        $this->revisions[$persistedRevision->id] = $persistedRevision;

        return $persistedPage;
    }

    public function updatePage(ContentPage $page): void
    {
        $this->pages[$page->pageKey] = $page;
    }

    public function createRevision(ContentPageRevision $revision): ContentPageRevision
    {
        $persisted = $revision->withPersistence($this->nextRevisionId++, $revision->pageId);
        $this->revisions[$persisted->id] = $persisted;

        return $persisted;
    }

    public function updateRevision(ContentPageRevision $revision): void
    {
        $this->revisions[$revision->id] = $revision;
    }

    public function findPageByKey(string $pageKey): ?ContentPage
    {
        return $this->pages[$pageKey] ?? null;
    }

    public function findPageByUuid(string $uuid): ?ContentPage
    {
        foreach ($this->pages as $page) {
            if ($page->uuid === $uuid) {
                return $page;
            }
        }

        return null;
    }

    public function findRevisionById(int $id): ?ContentPageRevision
    {
        return $this->revisions[$id] ?? null;
    }

    public function findRevisionByUuid(string $uuid): ?ContentPageRevision
    {
        foreach ($this->revisions as $revision) {
            if ($revision->uuid === $uuid) {
                return $revision;
            }
        }

        return null;
    }

    public function listRevisionsForPage(int $pageId): array
    {
        return array_values(array_filter(
            $this->revisions,
            static fn(ContentPageRevision $revision): bool => $revision->pageId === $pageId,
        ));
    }

    public function nextVersionNumber(int $pageId): int
    {
        $versions = array_map(
            static fn(ContentPageRevision $revision): int => $revision->versionNumber,
            $this->listRevisionsForPage($pageId),
        );

        return $versions === [] ? 1 : (max($versions) + 1);
    }

    public function listPages(): array
    {
        return array_values($this->pages);
    }
}

final class AdminFlowAuditService implements IAuditService
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}

final class AdminFlowMediaRepository implements IContentMediaRepository
{
    /** @var array<string, ContentMedia> */
    private array $items = [];
    private int $nextId = 100;

    /** @param list<ContentMedia> $items */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->items[$item->uuid] = $item;
        }
    }

    public function create(ContentMedia $media): ContentMedia
    {
        $persisted = new ContentMedia(
            id: $this->nextId++,
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
        $this->items[$persisted->uuid] = $persisted;

        return $persisted;
    }

    public function update(ContentMedia $media): void
    {
        $this->items[$media->uuid] = $media;
    }

    public function findByUuid(string $uuid): ?ContentMedia
    {
        return $this->items[$uuid] ?? null;
    }

    public function listSelectable(int $limit = 100, int $offset = 0): array
    {
        $items = array_values(array_filter($this->items, static fn(ContentMedia $item): bool => $item->status !== 'archived'));
        return array_slice($items, $offset, $limit);
    }
}
