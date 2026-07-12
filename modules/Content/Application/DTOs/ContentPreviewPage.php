<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Application\DTOs;

final class ContentPreviewPage
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        public readonly string $pageUuid,
        public readonly string $pageKey,
        public readonly string $routePath,
        public readonly string $title,
        public readonly string $revisionUuid,
        public readonly string $revisionStatus,
        public readonly int $versionNumber,
        public readonly array $snapshot,
        public readonly int $lockVersion,
        public readonly ?string $publishedAt,
    ) {}
}
