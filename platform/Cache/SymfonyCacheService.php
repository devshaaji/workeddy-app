<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Cache;

use WorkEddy\Platform\Config\ConfigLoader;

final class SymfonyCacheService implements ICacheService
{
    public function __construct(private readonly object $pool) {}

    public static function fromConfig(ConfigLoader $config): self
    {
        if (!class_exists(\Symfony\Component\Cache\Adapter\FilesystemAdapter::class)) {
            throw new \RuntimeException('Symfony Cache is not installed. Run composer install before using the production cache adapter.');
        }

        return new self(new \Symfony\Component\Cache\Adapter\FilesystemAdapter(
            namespace: (string) $config->get('cache.namespace', $config->get('APP_NAME', 'WorkEddy')),
            defaultLifetime: 0,
            directory: (string) $config->get('cache.path', dirname(__DIR__, 2) . '/var/cache'),
        ));
    }

    public function get(string $key, ?callable $compute = null, ?int $ttlSeconds = null): mixed
    {
        $item = $this->pool->getItem($this->normalizeKey($key));

        if ($item->isHit()) {
            return $item->get();
        }

        if ($compute !== null) {
            $value = $compute();
            $item->set($value);
            if ($ttlSeconds !== null) {
                $item->expiresAfter($ttlSeconds);
            }
            $this->pool->save($item);
            return $value;
        }

        return null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $item = $this->pool->getItem($this->normalizeKey($key));
        $item->set($value);
        $item->expiresAfter($ttlSeconds);
        $this->pool->save($item);
    }

    public function delete(string $key): void
    {
        $this->pool->deleteItem($this->normalizeKey($key));
    }

    public function deleteByTag(string $tag): void
    {
        if (method_exists($this->pool, 'clear')) {
            $this->pool->clear();
        }
    }

    private function normalizeKey(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9_.]/', '_', $key) ?: hash('sha256', $key);
    }
}
