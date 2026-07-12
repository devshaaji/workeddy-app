<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Lock;

interface LockManagerContract
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function synchronized(string $resource, callable $callback, int $ttlSeconds = 30): mixed;
}
