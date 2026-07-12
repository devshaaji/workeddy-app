<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

final class TransportDispatchReport
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly int $claimed,
        public readonly int $delivered,
        public readonly int $failed,
        public readonly int $retried,
        public readonly int $fallbacks,
        public readonly array $errors = [],
    ) {}
}
