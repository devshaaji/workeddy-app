<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Content\Application\Services\ContentWorkflowService;
use WorkEddy\Modules\Content\Domain\ContentPage;
use WorkEddy\Modules\Content\Domain\ContentPageRevision;
use WorkEddy\Modules\Content\Domain\Contracts\ContentPageSchema;
use WorkEddy\Modules\Content\Domain\Contracts\IContentPageRepository;
use WorkEddy\Modules\Content\Support\ContentPageSchemaRegistry;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\Content\Support\MethodologyPageSchema;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ValidationException;

final class ContentLifecycleTest extends TestCase
{
    public function test_create_page_creates_active_draft_and_save_updates_draft_only(): void
    {
        $repo = new InMemoryContentPageRepository();
        $audit = new RecordingContentAuditService();
        $service = $this->service($repo, $audit);

        $created = $service->createPage(
            pageKey: MethodologyPageDefinition::PAGE_KEY,
            routePath: '/content/methodology-and-limitations',
            contentType: 'managed_page',
            templateKey: 'internal_methodology',
            audience: 'internal',
            actorId: 77,
            title: 'Methodology and Limitations',
            snapshot: MethodologyPageDefinition::seedSnapshot(),
            changeSummary: 'Initial seeded publication draft.',
        );

        self::assertSame(1, $created->lockVersion);
        self::assertNull($created->publishedRevisionId);
        self::assertNotNull($created->draftRevisionId);

        $saved = $service->saveDraft(
            pageKey: MethodologyPageDefinition::PAGE_KEY,
            title: 'Methodology and Limitations v2',
            seoTitle: null,
            seoDescription: null,
            snapshot: MethodologyPageDefinition::seedSnapshotWithOverride('what_workeddy_measures', 'Updated methodology text.'),
            expectedLockVersion: 1,
            actorId: 77,
            changeSummary: 'Clarified methodology wording.',
        );

        self::assertSame('draft', $saved->revisionStatus);
        self::assertSame(2, $repo->pageByKey(MethodologyPageDefinition::PAGE_KEY)?->lockVersion);
        self::assertSame(1, $repo->revisionCountForPage(MethodologyPageDefinition::PAGE_KEY));
        self::assertSame(['content.page.created', 'content.draft.saved'], array_column($audit->records, 'action'));
    }

    public function test_publish_freezes_active_draft_and_follow_up_edit_creates_new_draft(): void
    {
        $repo = new InMemoryContentPageRepository();
        $service = $this->service($repo, new RecordingContentAuditService());
        $service->createPage(
            pageKey: MethodologyPageDefinition::PAGE_KEY,
            routePath: '/content/methodology-and-limitations',
            contentType: 'managed_page',
            templateKey: 'internal_methodology',
            audience: 'internal',
            actorId: 77,
            title: 'Methodology and Limitations',
            snapshot: MethodologyPageDefinition::seedSnapshot(),
            changeSummary: 'Initial draft.',
        );

        $published = $service->publishDraft(MethodologyPageDefinition::PAGE_KEY, actorId: 77, changeSummary: 'Initial publication.');

        self::assertSame(MethodologyPageDefinition::PAGE_KEY, $published->key);
        self::assertNotSame('', $published->revisionUuid);
        self::assertNotNull($repo->pageByKey(MethodologyPageDefinition::PAGE_KEY)?->publishedRevisionId);

        $draft = $service->beginDraftFromPublished(MethodologyPageDefinition::PAGE_KEY, actorId: 77);

        self::assertSame('draft', $draft->revisionStatus);
        self::assertSame(2, $repo->revisionCountForPage(MethodologyPageDefinition::PAGE_KEY));
    }

    public function test_restore_creates_new_draft_and_stale_lock_version_conflicts(): void
    {
        $repo = new InMemoryContentPageRepository();
        $service = $this->service($repo, new RecordingContentAuditService());
        $service->createPage(
            pageKey: MethodologyPageDefinition::PAGE_KEY,
            routePath: '/content/methodology-and-limitations',
            contentType: 'managed_page',
            templateKey: 'internal_methodology',
            audience: 'internal',
            actorId: 77,
            title: 'Methodology and Limitations',
            snapshot: MethodologyPageDefinition::seedSnapshot(),
            changeSummary: 'Initial draft.',
        );
        $service->publishDraft(MethodologyPageDefinition::PAGE_KEY, actorId: 77, changeSummary: 'Initial publication.');
        $service->beginDraftFromPublished(MethodologyPageDefinition::PAGE_KEY, actorId: 77);

        $page = $repo->pageByKey(MethodologyPageDefinition::PAGE_KEY);
        self::assertNotNull($page);
        $publishedRevision = $repo->publishedRevision(MethodologyPageDefinition::PAGE_KEY);
        self::assertNotNull($publishedRevision);

        $this->expectException(ConflictException::class);
        $service->saveDraft(
            pageKey: MethodologyPageDefinition::PAGE_KEY,
            title: 'Conflict',
            seoTitle: null,
            seoDescription: null,
            snapshot: MethodologyPageDefinition::seedSnapshot(),
            expectedLockVersion: $page->lockVersion - 1,
            actorId: 77,
            changeSummary: 'Conflicting save.',
        );

        $restored = $service->restoreRevision(
            pageKey: MethodologyPageDefinition::PAGE_KEY,
            revisionUuid: $publishedRevision->uuid,
            actorId: 77,
            changeSummary: 'Restore published revision into new draft.',
        );

        self::assertSame('draft', $restored->revisionStatus);
        self::assertGreaterThan(2, $repo->revisionCountForPage(MethodologyPageDefinition::PAGE_KEY));
    }

    public function test_methodology_schema_rejects_missing_duplicate_and_disallowed_sections(): void
    {
        $schema = new MethodologyPageSchema();

        $missingSection = MethodologyPageDefinition::seedSnapshot();
        array_pop($missingSection['sections']);

        $duplicateSection = MethodologyPageDefinition::seedSnapshot();
        $duplicateSection['sections'][] = $duplicateSection['sections'][0];

        $disallowedBlock = MethodologyPageDefinition::seedSnapshot();
        $disallowedBlock['sections'][0]['blocks'][] = ['type' => 'video', 'url' => 'https://example.test/video.mp4'];

        $this->assertFalse($schema->validate('draft', $missingSection)->isValid());
        $this->assertFalse($schema->validate('draft', $duplicateSection)->isValid());
        $this->assertFalse($schema->validate('draft', $disallowedBlock)->isValid());
        $this->assertTrue($schema->validate('published', MethodologyPageDefinition::seedSnapshot())->isValid());
    }

    public function test_create_page_rejects_invalid_seed_snapshot(): void
    {
        $service = $this->service(new InMemoryContentPageRepository(), new RecordingContentAuditService());

        $this->expectException(ValidationException::class);
        $service->createPage(
            pageKey: MethodologyPageDefinition::PAGE_KEY,
            routePath: '/content/methodology-and-limitations',
            contentType: 'managed_page',
            templateKey: 'internal_methodology',
            audience: 'internal',
            actorId: 77,
            title: 'Broken Methodology',
            snapshot: ['sections' => [], 'references' => []],
            changeSummary: 'Broken seed.',
        );
    }

    private function service(IContentPageRepository $repo, IAuditService $audit): ContentWorkflowService
    {
        return new ContentWorkflowService(
            $repo,
            new ContentPageSchemaRegistry([
                new MethodologyPageSchema(),
            ]),
            $audit,
        );
    }
}

final class InMemoryContentPageRepository implements IContentPageRepository
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

    public function pageByKey(string $pageKey): ?ContentPage
    {
        return $this->findPageByKey($pageKey);
    }

    public function revisionCountForPage(string $pageKey): int
    {
        $page = $this->pageByKey($pageKey);
        if ($page === null || $page->id === null) {
            return 0;
        }

        return count($this->listRevisionsForPage($page->id));
    }

    public function publishedRevision(string $pageKey): ?ContentPageRevision
    {
        $page = $this->pageByKey($pageKey);
        if ($page === null || $page->publishedRevisionId === null) {
            return null;
        }

        return $this->findRevisionById($page->publishedRevisionId);
    }
}

final class RecordingContentAuditService implements IAuditService
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}
