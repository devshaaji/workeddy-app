<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Config;

final class ConfigLoader
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    public function __construct(private readonly ?string $configPath = null) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        if (!is_string($file) || $file === '') {
            return $default;
        }

        $data = $this->load($file);
        foreach ($parts as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    public function has(string $key): bool
    {
        $missing = new \stdClass();

        return $this->get($key, $missing) !== $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(string $file): array
    {
        return $this->load($file);
    }

    /**
     * @return list<string>
     */
    public function configFiles(): array
    {
        $files = glob($this->path() . '/*.php') ?: [];

        return array_values(array_map(static fn(string $file): string => basename($file, '.php'), $files));
    }

    public function flush(): void
    {
        $this->loaded = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $file): array
    {
        if (isset($this->loaded[$file])) {
            return $this->loaded[$file];
        }

        $path = $this->path() . '/' . $file . '.php';
        if (!is_file($path)) {
            return $this->loaded[$file] = [];
        }

        $data = require $path;

        return $this->loaded[$file] = is_array($data) ? $data : [];
    }

    private function path(): string
    {
        return $this->configPath ?? dirname(__DIR__, 2) . '/config';
    }
}
