<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain\Contracts;

use WorkEddy\Modules\Content\Domain\ContentPage;
use WorkEddy\Modules\Content\Domain\ContentPageRevision;

interface IContentPageRepository
{
    public function createPage(ContentPage $page, ContentPageRevision $draftRevision): ContentPage;

    public function updatePage(ContentPage $page): void;

    public function createRevision(ContentPageRevision $revision): ContentPageRevision;

    public function updateRevision(ContentPageRevision $revision): void;

    public function findPageByKey(string $pageKey): ?ContentPage;

    public function findPageByUuid(string $uuid): ?ContentPage;

    public function findRevisionById(int $id): ?ContentPageRevision;

    public function findRevisionByUuid(string $uuid): ?ContentPageRevision;

    /** @return list<ContentPageRevision> */
    public function listRevisionsForPage(int $pageId): array;

    public function nextVersionNumber(int $pageId): int;

    /** @return list<ContentPage> */
    public function listPages(): array;
}
