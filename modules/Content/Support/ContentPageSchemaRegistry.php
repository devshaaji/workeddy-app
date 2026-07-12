<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Support;

use WorkEddy\Modules\Content\Domain\Contracts\ContentPageSchema;

final class ContentPageSchemaRegistry
{
    /** @var array<string, ContentPageSchema> */
    private array $schemas = [];

    /** @param list<ContentPageSchema> $schemas */
    public function __construct(array $schemas)
    {
        foreach ($schemas as $schema) {
            $this->schemas[$schema->pageKey()] = $schema;
        }
    }

    public function forPage(string $pageKey): ?ContentPageSchema
    {
        return $this->schemas[$pageKey] ?? null;
    }
}
