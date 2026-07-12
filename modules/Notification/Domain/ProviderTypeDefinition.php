<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class ProviderTypeDefinition
{
    public function __construct(
        public readonly string $type,
        public readonly array $channels,
        public readonly array $requiredFields,
        public readonly array $sensitiveFields,
        public readonly array $optionalFields,
    ) {}
}
