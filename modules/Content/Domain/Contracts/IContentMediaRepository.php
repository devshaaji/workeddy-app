<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain\Contracts;

use WorkEddy\Modules\Content\Domain\ContentMedia;

interface IContentMediaRepository
{
    public function create(ContentMedia $media): ContentMedia;

    public function update(ContentMedia $media): void;

    public function findByUuid(string $uuid): ?ContentMedia;

    /** @return list<ContentMedia> */
    public function listSelectable(int $limit = 100, int $offset = 0): array;
}
