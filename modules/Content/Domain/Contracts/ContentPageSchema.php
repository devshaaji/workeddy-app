<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Domain\Contracts;

use WorkEddy\Modules\Content\Support\ContentValidationResult;

interface ContentPageSchema
{
    public function pageKey(): string;

    public function validate(string $targetStatus, array $snapshot): ContentValidationResult;
}
