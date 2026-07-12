<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Content\Application\Services\ContentQueryService;
use WorkEddy\Modules\Content\Application\Services\ContentWorkflowService;
use WorkEddy\Modules\Content\Authorization\ContentPermissions;
use WorkEddy\Modules\Content\Domain\ContentPage;
use WorkEddy\Modules\Content\Domain\ContentPageRevision;
use WorkEddy\Modules\Content\Domain\ContentMedia;
use WorkEddy\Modules\Content\Domain\Contracts\IContentMediaRepository;
use WorkEddy\Modules\Content\Domain\Contracts\IContentPageRepository;
use WorkEddy\Modules\Content\Presentation\ContentApiController;
use WorkEddy\Modules\Content\Support\ContentPageSchemaRegistry;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\Content\Support\MethodologyPageSchema;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;

final class ContentApiFlowTest extends TestCase
{
    public function test_pages_and_page_scoped_revision_endpoints_return_success_payloads(): void
    {
        [$controller, $pages] = $this->controller();
        $page = $pages->listPages()[0];

        $pagesResponse = $controller->listPages(new Request('GET', '/api/v1/content/pages'));
        $historyResponse = $controller->listRevisions(new Request(
            'GET',
            '/api/v1/content/pages/' . $page->uuid . '/revisions',
            routeParams: ['pageUuid' => $page->uuid],
        ));
        $draftResponse = $controller->getDraft(new Request(
            'GET',
            '/api/v1/content/pages/' . $page->uuid . '/draft',
            routeParams: ['pageUuid' => $page->uuid],
        ));

        self::assertSame(200, $pagesResponse->getStatusCode());
        self::assertSame(200, $historyResponse->getStatusCode());
        self::assertSame(200, $draftResponse->getStatusCode());

        $pagesPayload = json_decode($pagesResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $historyPayload = json_decode($historyResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $draftPayload = json_decode($draftResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $pagesPayload['status']);
        self::assertSame(MethodologyPageDefinition::PAGE_KEY, $pagesPayload['data']['pages'][0]['pageKey']);
        self::assertSame('ok', $historyPayload['status']);
        self::assertNotSame([], $historyPayload['data']['history']);
        self::assertSame($page->uuid, $draftPayload['data']['draft']['pageUuid']);
    }

    public function test_archive_page_and_update_media_endpoints_return_updated_state(): void
    {
        [$controller, $pages] = $this->controller();
        $page = $pages->listPages()[0];

        $archiveResponse = $controller->archivePage(new Request(
            'POST',
            '/api/v1/content/pages/' . $page->uuid . '/archive',
            routeParams: ['pageUuid' => $page->uuid],
        ));
        $mediaUpdateResponse = $controller->updateMedia(new Request(
            'PUT',
            '/api/v1/content/media/media-1',
            body: ['defaultAltText' => 'updated alt', 'defaultCaption' => 'updated caption'],
            routeParams: ['mediaUuid' => 'media-1'],
        ));
        $mediaArchiveResponse = $controller->archiveMedia(new Request(
            'POST',
            '/api/v1/content/media/media-1/archive',
            routeParams: ['mediaUuid' => 'media-1'],
        ));

        $archivePayload = json_decode($archiveResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $mediaUpdatePayload = json_decode($mediaUpdateResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $mediaArchivePayload = json_decode($mediaArchiveResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('archived', $archivePayload['data']['status']);
        self::assertSame($page->uuid, $archivePayload['data']['pageUuid']);
        self::assertSame('updated alt', $mediaUpdatePayload['data']['defaultAltText']);
        self::assertSame('archived', $mediaArchivePayload['data']['status']);
    }

    public function test_create_page_endpoint_creates_a_second_generic_content_page(): void
    {
        [$controller, $pages] = $this->controller();

        $response = $controller->createPage(new Request(
            'POST',
            '/api/v1/content/pages',
            body: [
                'pageKey' => 'pilot-summary',
                'routePath' => '/content/pilot-summary',
                'contentType' => 'structured-page',
                'templateKey' => 'internal_default',
                'audience' => 'internal',
                'title' => 'Pilot Summary',
                'snapshot' => [
                    'sections' => [
                        [
                            'sectionKey' => 'summary',
                            'heading' => 'Summary',
                            'displayOrder' => 1,
                            'blocks' => [
                                ['type' => 'paragraph', 'text' => 'Initial draft.'],
                            ],
                        ],
                    ],
                    'references' => [],
                ],
            ],
        ));

        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('pilot-summary', $payload['data']['pageKey']);
        self::assertSame('pilot-summary', $pages->findPageByKey('pilot-summary')?->pageKey);
        self::assertCount(2, $pages->listPages());
    }

    public function test_save_draft_normalizes_quill_delta_snapshot_into_canonical_blocks(): void
    {
        [$controller, $pages] = $this->controller();
        $page = $pages->listPages()[0];
        $snapshot = MethodologyPageDefinition::seedSnapshot();

        foreach ($snapshot['sections'] as $index => $section) {
            $text = (string) ($section['blocks'][0]['text'] ?? '');
            $ops = [['insert' => $text . "\n"]];

            if ($index === 0) {
                $ops = [
                    ['insert' => "Updated methodology text.\n"],
                    ['insert' => [
                        'contentImage' => [
                            'mediaUuid' => 'media-1',
                            'altText' => 'Reviewer validating a posture frame',
                            'caption' => 'Inserted from editor.',
                            'display' => 'wide',
                        ],
                    ]],
                    ['insert' => "\n"],
                ];
            }

            unset($snapshot['sections'][$index]['blocks']);
            $snapshot['sections'][$index]['content'] = [
                'format' => 'quill_delta',
                'delta' => ['ops' => $ops],
            ];
        }

        $snapshot['references'][] = [
            '_key' => 'ref-1',
            'sectionKey' => 'what_workeddy_measures',
            'title' => 'Reference title',
            'author' => 'Research Team',
            'year' => '2026',
            'url' => 'https://example.test/reference',
            'citation' => 'Reference citation',
            'displayOrder' => 1,
        ];

        $response = $controller->saveDraft(new Request(
            'PUT',
            '/api/v1/content/pages/' . $page->uuid . '/draft',
            body: [
                'title' => 'Methodology and Limitations',
                'snapshot' => $snapshot,
                'expectedLockVersion' => 3,
            ],
            routeParams: ['pageUuid' => $page->uuid],
        ));

        self::assertSame(200, $response->getStatusCode());

        $draftPage = $pages->findPageByKey(MethodologyPageDefinition::PAGE_KEY);
        self::assertNotNull($draftPage);
        self::assertNotNull($draftPage->draftRevisionId);
        $draftRevision = $pages->findRevisionById($draftPage->draftRevisionId);
        self::assertNotNull($draftRevision);
        self::assertArrayNotHasKey('content', $draftRevision->contentSnapshot['sections'][0]);
        self::assertSame('Updated methodology text.', $draftRevision->contentSnapshot['sections'][0]['blocks'][0]['text'] ?? null);
        self::assertSame('image', $draftRevision->contentSnapshot['sections'][0]['blocks'][1]['type'] ?? null);
        self::assertSame('media-1', $draftRevision->contentSnapshot['sections'][0]['blocks'][1]['mediaUuid'] ?? null);
        self::assertSame('Reference title', $draftRevision->contentSnapshot['references'][1]['title'] ?? null);
    }

    public function test_create_page_with_references_requires_reference_manage_privilege(): void
    {
        [$controller] = $this->controller([
            ContentPermissions::PAGES_READ,
            ContentPermissions::PAGES_CREATE,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing privilege ' . ContentPermissions::REFERENCES_MANAGE);

        $controller->createPage(new Request(
            'POST',
            '/api/v1/content/pages',
            body: [
                'pageKey' => 'pilot-evidence',
                'routePath' => '/content/pilot-evidence',
                'contentType' => 'structured-page',
                'templateKey' => 'internal_default',
                'audience' => 'internal',
                'title' => 'Pilot Evidence',
                'snapshot' => [
                    'sections' => [
                        [
                            'sectionKey' => 'summary',
                            'heading' => 'Summary',
                            'displayOrder' => 1,
                            'blocks' => [
                                ['type' => 'paragraph', 'text' => 'Initial draft.'],
                            ],
                        ],
                    ],
                    'references' => [
                        [
                            'sectionKey' => 'summary',
                            'title' => 'Reference title',
                            'citation' => 'Reference citation',
                            'displayOrder' => 1,
                        ],
                    ],
                ],
            ],
        ));
    }

    public function test_save_draft_reference_changes_require_reference_manage_privilege(): void
    {
        [$controller, $pages] = $this->controller([
            ContentPermissions::PAGES_READ,
            ContentPermissions::PAGES_UPDATE,
            ContentPermissions::PREVIEW,
        ]);
        $page = $pages->listPages()[0];
        $snapshot = MethodologyPageDefinition::seedSnapshot();
        $snapshot['references'][] = [
            'sectionKey' => 'what_workeddy_measures',
            'title' => 'Reference title',
            'citation' => 'Reference citation',
            'displayOrder' => 2,
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing privilege ' . ContentPermissions::REFERENCES_MANAGE);

        $controller->saveDraft(new Request(
            'PUT',
            '/api/v1/content/pages/' . $page->uuid . '/draft',
            body: [
                'title' => 'Methodology and Limitations',
                'snapshot' => $snapshot,
                'expectedLockVersion' => 3,
            ],
            routeParams: ['pageUuid' => $page->uuid],
        ));
    }

    /**
     * @return array{0:ContentApiController,1:ApiFlowPageRepository,2:ApiFlowMediaRepository}
     */
    private function controller(?array $privileges = null): array
    {
        $pages = new ApiFlowPageRepository();
        $media = new ApiFlowMediaRepository([
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
        $workflow = new ContentWorkflowService(
            $pages,
            new ContentPageSchemaRegistry([new MethodologyPageSchema()]),
            new ApiFlowAuditService(),
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

        $grantedPrivileges = $privileges ?? [
            ContentPermissions::PAGES_READ,
            ContentPermissions::PAGES_CREATE,
            ContentPermissions::PAGES_UPDATE,
            ContentPermissions::PAGES_PUBLISH,
            ContentPermissions::PAGES_RESTORE,
            ContentPermissions::PAGES_ARCHIVE,
            ContentPermissions::REFERENCES_MANAGE,
            ContentPermissions::MEDIA_READ,
            ContentPermissions::MEDIA_UPDATE,
            ContentPermissions::MEDIA_ARCHIVE,
            ContentPermissions::PREVIEW,
        ];

        $session = new class($grantedPrivileges) implements ISessionService {
            /** @param list<string> $privileges */
            public function __construct(private readonly array $privileges) {}
            public function getUserContext(): ?UserContext
            {
                return new UserContext(userId: 7, organizationId: 3, organizationUuid: 'org-1', roleType: 'organization_admin', privileges: $this->privileges);
            }
            public function setUserContext(UserContext $context): void {}
            public function regenerate(): void {}
            public function destroy(): void {}
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value): void {}
        };
        $permissions = new class implements IPermissionService {
            public function requirePrivilege(UserContext $ctx, string $privilege): void
            {
                if (!in_array($privilege, $ctx->privileges, true)) {
                    throw new \RuntimeException('Missing privilege ' . $privilege);
                }
            }
        };
        $storage = $this->createMock(IStorageService::class);

        $controller = new ContentApiController(
            $session,
            $permissions,
            $workflow,
            new ContentQueryService($pages),
            $media,
            $storage,
            new ApiFlowAuditService(),
        );

        return [$controller, $pages, $media];
    }
}

final class ApiFlowPageRepository implements IContentPageRepository
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

    public function updatePage(ContentPage $page): void { $this->pages[$page->pageKey] = $page; }
    public function createRevision(ContentPageRevision $revision): ContentPageRevision
    {
        $persisted = $revision->withPersistence($this->nextRevisionId++, $revision->pageId);
        $this->revisions[$persisted->id] = $persisted;
        return $persisted;
    }
    public function updateRevision(ContentPageRevision $revision): void { $this->revisions[$revision->id] = $revision; }
    public function findPageByKey(string $pageKey): ?ContentPage { return $this->pages[$pageKey] ?? null; }
    public function findPageByUuid(string $uuid): ?ContentPage
    {
        foreach ($this->pages as $page) {
            if ($page->uuid === $uuid) {
                return $page;
            }
        }
        return null;
    }
    public function findRevisionById(int $id): ?ContentPageRevision { return $this->revisions[$id] ?? null; }
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
        return array_values(array_filter($this->revisions, static fn(ContentPageRevision $revision): bool => $revision->pageId === $pageId));
    }
    public function nextVersionNumber(int $pageId): int
    {
        $versions = array_map(static fn(ContentPageRevision $revision): int => $revision->versionNumber, $this->listRevisionsForPage($pageId));
        return $versions === [] ? 1 : (max($versions) + 1);
    }
    public function listPages(): array { return array_values($this->pages); }
}

final class ApiFlowMediaRepository implements IContentMediaRepository
{
    /** @var array<string, ContentMedia> */
    private array $items = [];

    /** @param list<ContentMedia> $items */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->items[$item->uuid] = $item;
        }
    }

    public function create(ContentMedia $media): ContentMedia { $this->items[$media->uuid] = $media; return $media; }
    public function update(ContentMedia $media): void { $this->items[$media->uuid] = $media; }
    public function findByUuid(string $uuid): ?ContentMedia { return $this->items[$uuid] ?? null; }
    public function listSelectable(int $limit = 100, int $offset = 0): array
    {
        return array_values(array_filter($this->items, static fn(ContentMedia $item): bool => $item->status !== 'archived'));
    }
}

final class ApiFlowAuditService implements IAuditService
{
    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void {}
}
