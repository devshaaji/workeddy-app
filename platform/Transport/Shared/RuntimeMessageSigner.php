<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

final class RuntimeMessageSigner
{
    public function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public function verify(string $secret, string $timestamp, string $body, string $signature): bool
    {
        return hash_equals($this->sign($secret, $timestamp, $body), $signature);
    }
}
