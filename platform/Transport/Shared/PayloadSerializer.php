<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

final class PayloadSerializer
{
    /**
     * @param array<string, mixed> $value
     */
    public function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    public function encodeRaw(array|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
