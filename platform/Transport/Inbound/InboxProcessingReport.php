<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class InboxProcessingReport
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly int $claimed,
        public readonly int $processed,
        public readonly int $failed,
        public readonly int $retried,
        public readonly int $rejected,
        public readonly int $acksPublished,
        public readonly array $errors = [],
    ) {}
}
