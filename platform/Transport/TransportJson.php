<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

final class TransportJson
{
    /**
     * @param array<string, mixed> $value
     */
    public static function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
