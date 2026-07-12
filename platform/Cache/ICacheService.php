<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Cache;

interface ICacheService
{
    public function get(string $key, ?callable $compute = null, ?int $ttlSeconds = null): mixed;

    public function set(string $key, mixed $value, int $ttlSeconds): void;

    public function delete(string $key): void;

    public function deleteByTag(string $tag): void;
}
