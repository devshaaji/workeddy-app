<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Content\Domain\ContentPage;
use WorkEddy\Modules\Content\Domain\ContentPageRevision;
use WorkEddy\Modules\Content\Domain\Contracts\IContentPageRepository;

final class ContentPageRepository implements IContentPageRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function createPage(ContentPage $page, ContentPageRevision $draftRevision): ContentPage
    {
        $this->connection->insert('content_pages', [
            'uuid' => $page->uuid,
            'page_key' => $page->pageKey,
            'route_path' => $page->routePath,
            'content_type' => $page->contentType,
            'template_key' => $page->templateKey,
            'audience' => $page->audience,
            'status' => $page->status,
            'published_revision_id' => null,
            'draft_revision_id' => null,
            'lock_version' => $page->lockVersion,
            'created_by' => $page->createdBy,
            'created_at' => $page->createdAt,
            'updated_at' => $page->updatedAt,
            'archived_at' => $page->archivedAt,
            'archived_by' => $page->archivedBy,
        ]);

        $pageId = (int) $this->connection->lastInsertId();
        $persistedRevision = $this->createRevision($draftRevision->withPersistence($draftRevision->id ?? 0, $pageId));
        $persistedPage = $page->withPersistence($pageId, $persistedRevision->id ?? 0);
        $this->updatePage($persistedPage);

        return $persistedPage;
    }

    public function updatePage(ContentPage $page): void
    {
        if ($page->id === null) {
            throw new \InvalidArgumentException('Cannot update a content page without an internal id.');
        }

        $this->connection->update('content_pages', [
            'status' => $page->status,
            'published_revision_id' => $page->publishedRevisionId,
            'draft_revision_id' => $page->draftRevisionId,
            'lock_version' => $page->lockVersion,
            'updated_at' => $page->updatedAt,
            'archived_at' => $page->archivedAt,
            'archived_by' => $page->archivedBy,
        ], ['id' => $page->id]);
    }

    public function createRevision(ContentPageRevision $revision): ContentPageRevision
    {
        $pageId = $revision->pageId;
        if ($pageId === null || $pageId <= 0) {
            throw new \InvalidArgumentException('Cannot create a content revision without a page id.');
        }

        $this->connection->insert('content_page_revisions', [
            'uuid' => $revision->uuid,
            'page_id' => $pageId,
            'version_number' => $revision->versionNumber,
            'revision_status' => $revision->revisionStatus,
            'title' => $revision->title,
            'seo_title' => $revision->seoTitle,
            'seo_description' => $revision->seoDescription,
            'change_summary' => $revision->changeSummary,
            'content_snapshot' => json_encode($revision->contentSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $revision->createdBy,
            'created_at' => $revision->createdAt,
            'updated_by' => $revision->updatedBy,
            'updated_at' => $revision->updatedAt,
            'published_by' => $revision->publishedBy,
            'published_at' => $revision->publishedAt,
        ]);

        return $revision->withPersistence((int) $this->connection->lastInsertId(), $pageId);
    }

    public function updateRevision(ContentPageRevision $revision): void
    {
        if ($revision->id === null) {
            throw new \InvalidArgumentException('Cannot update a content revision without an internal id.');
        }

        $this->connection->update('content_page_revisions', [
            'revision_status' => $revision->revisionStatus,
            'title' => $revision->title,
            'seo_title' => $revision->seoTitle,
            'seo_description' => $revision->seoDescription,
            'change_summary' => $revision->changeSummary,
            'content_snapshot' => json_encode($revision->contentSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_by' => $revision->updatedBy,
            'updated_at' => $revision->updatedAt,
            'published_by' => $revision->publishedBy,
            'published_at' => $revision->publishedAt,
        ], ['id' => $revision->id]);
    }

    public function findPageByKey(string $pageKey): ?ContentPage
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_pages WHERE page_key = ? LIMIT 1', [$pageKey]);

        return is_array($row) ? $this->hydratePage($row) : null;
    }

    public function findPageByUuid(string $uuid): ?ContentPage
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_pages WHERE uuid = ? LIMIT 1', [$uuid]);

        return is_array($row) ? $this->hydratePage($row) : null;
    }

    public function findRevisionById(int $id): ?ContentPageRevision
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_page_revisions WHERE id = ? LIMIT 1', [$id]);

        return is_array($row) ? $this->hydrateRevision($row) : null;
    }

    public function findRevisionByUuid(string $uuid): ?ContentPageRevision
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_page_revisions WHERE uuid = ? LIMIT 1', [$uuid]);

        return is_array($row) ? $this->hydrateRevision($row) : null;
    }

    public function listRevisionsForPage(int $pageId): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM content_page_revisions WHERE page_id = ? ORDER BY version_number DESC, id DESC', [$pageId]);

        return array_map(fn(array $row): ContentPageRevision => $this->hydrateRevision($row), $rows);
    }

    public function nextVersionNumber(int $pageId): int
    {
        $value = $this->connection->fetchOne('SELECT MAX(version_number) FROM content_page_revisions WHERE page_id = ?', [$pageId]);

        return $value === false || $value === null ? 1 : ((int) $value + 1);
    }

    public function listPages(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM content_pages ORDER BY updated_at DESC, id DESC');

        return array_map(fn(array $row): ContentPage => $this->hydratePage($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function hydratePage(array $row): ContentPage
    {
        return new ContentPage(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) $row['uuid'],
            pageKey: (string) $row['page_key'],
            routePath: (string) $row['route_path'],
            contentType: (string) $row['content_type'],
            templateKey: (string) $row['template_key'],
            audience: (string) $row['audience'],
            status: (string) $row['status'],
            publishedRevisionId: isset($row['published_revision_id']) ? (int) $row['published_revision_id'] : null,
            draftRevisionId: isset($row['draft_revision_id']) ? (int) $row['draft_revision_id'] : null,
            lockVersion: (int) ($row['lock_version'] ?? 0),
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt: self::stringDate($row['created_at'] ?? null),
            updatedAt: self::stringDate($row['updated_at'] ?? null),
            archivedAt: self::nullableStringDate($row['archived_at'] ?? null),
            archivedBy: isset($row['archived_by']) && $row['archived_by'] !== null ? (int) $row['archived_by'] : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateRevision(array $row): ContentPageRevision
    {
        $snapshot = json_decode((string) ($row['content_snapshot'] ?? '{}'), true);
        if (!is_array($snapshot)) {
            $snapshot = ['sections' => [], 'references' => []];
        }

        return new ContentPageRevision(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) $row['uuid'],
            pageId: isset($row['page_id']) ? (int) $row['page_id'] : null,
            versionNumber: (int) ($row['version_number'] ?? 1),
            revisionStatus: (string) ($row['revision_status'] ?? 'draft'),
            title: (string) ($row['title'] ?? ''),
            seoTitle: isset($row['seo_title']) ? (string) $row['seo_title'] : null,
            seoDescription: isset($row['seo_description']) ? (string) $row['seo_description'] : null,
            changeSummary: isset($row['change_summary']) ? (string) $row['change_summary'] : null,
            contentSnapshot: $snapshot,
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt: self::stringDate($row['created_at'] ?? null),
            updatedBy: isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            updatedAt: self::nullableStringDate($row['updated_at'] ?? null),
            publishedBy: isset($row['published_by']) ? (int) $row['published_by'] : null,
            publishedAt: self::nullableStringDate($row['published_at'] ?? null),
        );
    }

    private static function stringDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private static function nullableStringDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
