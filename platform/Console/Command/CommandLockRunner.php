<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Console\Command;

use WorkEddy\Platform\Lock\LockManagerContract;

final class CommandLockRunner
{
    public function __construct(private readonly LockManagerContract $locks) {}

    /**
     * @param callable(): int $operation
     */
    public function run(string $resource, int $ttl, callable $operation): int
    {
        return (int) $this->locks->synchronized($resource, $operation, $ttl);
    }

    public function scopedResource(string $scope, string ...$parts): string
    {
        $scope = trim($scope, ':');
        if ($parts === []) {
            return $scope;
        }

        return $scope . ':' . substr(hash('sha256', implode("\0", $parts)), 0, 16);
    }
}
