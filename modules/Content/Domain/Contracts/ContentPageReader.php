<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain\Contracts;

use WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage;

interface ContentPageReader
{
    public function findPublishedByKey(string $pageKey): ?PublishedContentPage;
}
