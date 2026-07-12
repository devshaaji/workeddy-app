<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain\Contracts;

use WorkEddy\Modules\Content\Application\DTOs\ContentPreviewPage;

interface ContentPreviewReader
{
    public function findDraftByKey(string $pageKey): ?ContentPreviewPage;

    public function findRevisionByUuid(string $revisionUuid): ?ContentPreviewPage;
}
