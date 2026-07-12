<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

final class HeaderSanitizer
{
    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function sanitize(array $headers): array
    {
        $sanitized = [];
        foreach ($headers as $key => $value) {
            $name = (string) $key;
            $sanitized[$name] = preg_match('/authorization|api-key|secret|token|password/i', $name) === 1
                ? '[redacted]'
                : $value;
        }

        return $sanitized;
    }
}
