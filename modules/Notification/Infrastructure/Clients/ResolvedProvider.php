<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients;

use WorkEddy\Modules\Notification\Domain\ProviderEntry;

final class ResolvedProvider
{
    public function __construct(
        public readonly object $client,
        public readonly ProviderEntry $entry,
    ) {}
}
