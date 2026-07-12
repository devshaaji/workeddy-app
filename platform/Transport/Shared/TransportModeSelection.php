<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

final class TransportModeSelection
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $mode,
        public readonly ?string $endpoint,
        public readonly ?string $reason = null,
    ) {}
}
