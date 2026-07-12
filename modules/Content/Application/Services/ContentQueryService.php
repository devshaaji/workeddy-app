<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Application\Services;

use WorkEddy\Modules\Content\Application\DTOs\ContentPreviewPage;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentReference;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentSection;
use WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader;
use WorkEddy\Modules\Content\Domain\Contracts\ContentPreviewReader;
use WorkEddy\Modules\Content\Domain\Contracts\IContentPageRepository;

final class ContentQueryService implements ContentPageReader, ContentPreviewReader
{
    public function __construct(private readonly IContentPageRepository $pages)
    {
    }

    public function findPublishedByKey(string $pageKey): ?PublishedContentPage
    {
        $page = $this->pages->findPageByKey($pageKey);
        if ($page === null || $page->status === 'archived' || $page->publishedRevisionId === null) {
            return null;
        }

        $revision = $this->pages->findRevisionById($page->publishedRevisionId);
        if ($revision === null || $revision->publishedAt === null) {
            return null;
        }

        $sections = [];
        foreach (($revision->contentSnapshot['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $plainText = $this->blocksToPlainText((array) ($section['blocks'] ?? []));
            $sections[] = new PublishedContentSection(
                (string) ($section['sectionKey'] ?? ''),
                (string) ($section['heading'] ?? ''),
                is_array($section['blocks'] ?? null) ? array_values($section['blocks']) : [],
                (int) ($section['displayOrder'] ?? 0),
                $plainText,
            );
        }

        $references = [];
        foreach (($revision->contentSnapshot['references'] ?? []) as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            $references[] = new PublishedContentReference(
                isset($reference['sectionKey']) ? (string) $reference['sectionKey'] : null,
                (string) ($reference['title'] ?? ''),
                isset($reference['author']) ? (string) $reference['author'] : null,
                isset($reference['year']) ? (string) $reference['year'] : null,
                isset($reference['url']) ? (string) $reference['url'] : null,
                isset($reference['citation']) ? (string) $reference['citation'] : null,
                (int) ($reference['displayOrder'] ?? 0),
            );
        }

        return new PublishedContentPage(
            key: $page->pageKey,
            title: $revision->title,
            audience: $page->audience,
            templateKey: $page->templateKey,
            sections: $sections,
            references: $references,
            images: [],
            revisionUuid: $revision->uuid,
            publishedAt: new \DateTimeImmutable($revision->publishedAt),
            snapshotHash: hash('sha256', (string) json_encode($revision->contentSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        );
    }

    public function findDraftByKey(string $pageKey): ?ContentPreviewPage
    {
        $page = $this->pages->findPageByKey($pageKey);
        if ($page === null) {
            return null;
        }

        return $this->buildDraftPreview($page);
    }

    public function findDraftByUuid(string $pageUuid): ?ContentPreviewPage
    {
        $page = $this->pages->findPageByUuid($pageUuid);
        if ($page === null) {
            return null;
        }

        return $this->buildDraftPreview($page);
    }

    public function findRevisionForPage(string $pageUuid, string $revisionUuid): ?ContentPreviewPage
    {
        $page = $this->pages->findPageByUuid($pageUuid);
        if ($page === null) {
            return null;
        }

        $revision = $this->pages->findRevisionByUuid($revisionUuid);
        if ($revision === null || $revision->pageId !== $page->id) {
            return null;
        }

        return new ContentPreviewPage($page->uuid, $page->pageKey, $page->routePath, $revision->title, $revision->uuid, $revision->revisionStatus, $revision->versionNumber, $revision->contentSnapshot, $page->lockVersion, $revision->publishedAt);
    }

    public function findRevisionByUuid(string $revisionUuid): ?ContentPreviewPage
    {
        $revision = $this->pages->findRevisionByUuid($revisionUuid);
        if ($revision === null) {
            return null;
        }

        $page = null;
        foreach ($this->pages->listPages() as $candidate) {
            if ($candidate->id === $revision->pageId) {
                $page = $candidate;
                break;
            }
        }
        if ($page === null) {
            return null;
        }

        return new ContentPreviewPage($page->uuid, $page->pageKey, $page->routePath, $revision->title, $revision->uuid, $revision->revisionStatus, $revision->versionNumber, $revision->contentSnapshot, $page->lockVersion, $revision->publishedAt);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPages(): array
    {
        if (!method_exists($this->pages, 'listPages')) {
            $page = $this->findPublishedByKey(\WorkEddy\Modules\Content\Support\MethodologyPageDefinition::PAGE_KEY);
            return $page === null ? [] : [[
                'pageKey' => $page->key,
                'title' => $page->title,
                'audience' => $page->audience,
                'templateKey' => $page->templateKey,
                'publishedRevisionUuid' => $page->revisionUuid,
            ]];
        }

        /** @var list<\WorkEddy\Modules\Content\Domain\ContentPage> $pages */
        $pages = $this->pages->listPages();
        $items = [];
        foreach ($pages as $page) {
            $publishedRevision = $page->publishedRevisionId !== null ? $this->pages->findRevisionById($page->publishedRevisionId) : null;
            $draftRevision = $page->draftRevisionId !== null ? $this->pages->findRevisionById($page->draftRevisionId) : null;
            $items[] = [
                'pageUuid' => $page->uuid,
                'pageKey' => $page->pageKey,
                'routePath' => $page->routePath,
                'audience' => $page->audience,
                'status' => $page->status,
                'lockVersion' => $page->lockVersion,
                'publishedRevisionUuid' => $publishedRevision?->uuid,
                'draftRevisionUuid' => $draftRevision?->uuid,
                'title' => $draftRevision?->title ?? $publishedRevision?->title ?? $page->pageKey,
                'templateKey' => $page->templateKey,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRevisionHistory(string $pageKey): array
    {
        $page = $this->pages->findPageByKey($pageKey);
        if ($page === null) {
            return [];
        }

        return $this->buildRevisionHistory($page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRevisionHistoryByPageUuid(string $pageUuid): array
    {
        $page = $this->pages->findPageByUuid($pageUuid);
        if ($page === null || $page->id === null) {
            return [];
        }

        return $this->buildRevisionHistory($page);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPageSummaryByUuid(string $pageUuid): ?array
    {
        $page = $this->pages->findPageByUuid($pageUuid);
        if ($page === null) {
            return null;
        }

        $publishedRevision = $page->publishedRevisionId !== null ? $this->pages->findRevisionById($page->publishedRevisionId) : null;
        $draftRevision = $page->draftRevisionId !== null ? $this->pages->findRevisionById($page->draftRevisionId) : null;

        return [
            'pageUuid' => $page->uuid,
            'pageKey' => $page->pageKey,
            'routePath' => $page->routePath,
            'contentType' => $page->contentType,
            'templateKey' => $page->templateKey,
            'audience' => $page->audience,
            'status' => $page->status,
            'lockVersion' => $page->lockVersion,
            'title' => $draftRevision?->title ?? $publishedRevision?->title ?? $page->pageKey,
            'draftRevisionUuid' => $draftRevision?->uuid,
            'publishedRevisionUuid' => $publishedRevision?->uuid,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPageSummaryByKey(string $pageKey): ?array
    {
        $page = $this->pages->findPageByKey($pageKey);
        if ($page === null) {
            return null;
        }

        $publishedRevision = $page->publishedRevisionId !== null ? $this->pages->findRevisionById($page->publishedRevisionId) : null;
        $draftRevision = $page->draftRevisionId !== null ? $this->pages->findRevisionById($page->draftRevisionId) : null;

        return [
            'pageUuid' => $page->uuid,
            'pageKey' => $page->pageKey,
            'routePath' => $page->routePath,
            'contentType' => $page->contentType,
            'templateKey' => $page->templateKey,
            'audience' => $page->audience,
            'status' => $page->status,
            'lockVersion' => $page->lockVersion,
            'title' => $draftRevision?->title ?? $publishedRevision?->title ?? $page->pageKey,
            'draftRevisionUuid' => $draftRevision?->uuid,
            'publishedRevisionUuid' => $publishedRevision?->uuid,
        ];
    }

    private function buildDraftPreview(\WorkEddy\Modules\Content\Domain\ContentPage $page): ?ContentPreviewPage
    {
        if ($page->draftRevisionId === null) {
            return null;
        }

        $revision = $this->pages->findRevisionById($page->draftRevisionId);
        if ($revision === null) {
            return null;
        }

        return new ContentPreviewPage($page->uuid, $page->pageKey, $page->routePath, $revision->title, $revision->uuid, $revision->revisionStatus, $revision->versionNumber, $revision->contentSnapshot, $page->lockVersion, $revision->publishedAt);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRevisionHistory(\WorkEddy\Modules\Content\Domain\ContentPage $page): array
    {
        $history = [];
        foreach ($this->pages->listRevisionsForPage($page->id) as $revision) {
            $history[] = [
                'revisionUuid' => $revision->uuid,
                'revisionStatus' => $revision->revisionStatus,
                'versionNumber' => $revision->versionNumber,
                'title' => $revision->title,
                'publishedAt' => $revision->publishedAt,
                'updatedAt' => $revision->updatedAt,
                'changeSummary' => $revision->changeSummary,
            ];
        }

        usort($history, static fn(array $a, array $b): int => $b['versionNumber'] <=> $a['versionNumber']);

        return $history;
    }

    /** @param list<array<string, mixed>> $blocks */
    private function blocksToPlainText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? '');
            if ($type === 'paragraph') {
                $parts[] = trim((string) ($block['text'] ?? ''));
                continue;
            }
            if ($type === 'rich_text') {
                $parts[] = trim((string) ($block['body'] ?? ''));
                continue;
            }
            if ($type === 'list') {
                foreach (($block['items'] ?? []) as $item) {
                    $parts[] = trim((string) $item);
                }
            }
        }

        return trim(implode("\n", array_filter($parts, static fn(string $value): bool => $value !== '')));
    }
}
