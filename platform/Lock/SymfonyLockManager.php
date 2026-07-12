<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Lock;

use WorkEddy\Platform\Config\ConfigLoader;

final class SymfonyLockManager implements LockManagerContract
{
    public function __construct(private readonly object $factory) {}

    public static function fromConfig(ConfigLoader $config): self
    {
        if (!class_exists(\Symfony\Component\Lock\LockFactory::class) || !class_exists(\Symfony\Component\Lock\Store\FlockStore::class)) {
            throw new \RuntimeException('Symfony Lock is not installed. Run composer install before using production locks.');
        }

        $directory = (string) $config->get('lock.path', dirname(__DIR__, 2) . '/var/locks');

        return new self(new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\FlockStore($directory)));
    }

    public function synchronized(string $resource, callable $callback, int $ttlSeconds = 30): mixed
    {
        $lock = $this->factory->createLock($resource, $ttlSeconds);
        if (!$lock->acquire(true)) {
            throw new \RuntimeException('Could not acquire lock: ' . $resource);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
