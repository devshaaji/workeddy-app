<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Domain;

final class RecommendationRule
{
    /** @param array<string, mixed> $condition @param array<string, mixed> $action */
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly array $condition,
        public readonly array $action,
        public readonly int $weight,
        public readonly bool $isActive,
        public readonly ?string $updatedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'condition' => $this->condition,
            'action' => $this->action,
            'weight' => $this->weight,
            'isActive' => $this->isActive,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
