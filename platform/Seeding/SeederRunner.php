<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Seeding;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class SeederRunner
{
    public function __construct(private readonly string $seedsDir) {}

    /** @return list<string> names of seeders that were run */
    public function run(Connection $db, ?string $filter = null): array
    {
        $files = $this->discover($filter);
        $ran = [];

        foreach ($files as $file) {
            $seeder = require $file;

            if (!$seeder instanceof SeederInterface) {
                throw new RuntimeException("Seeder file must return a SeederInterface instance: {$file}");
            }

            $seeder->run($db);
            $ran[] = basename($file);
        }

        return $ran;
    }

    /** @return list<string> */
    public function list(?string $filter = null): array
    {
        return array_map('basename', $this->discover($filter));
    }

    /** @return list<string> */
    private function discover(?string $filter): array
    {
        if (!is_dir($this->seedsDir)) {
            throw new RuntimeException("Seeds directory not found: {$this->seedsDir}");
        }

        $files = glob($this->seedsDir . '/*.php') ?: [];
        sort($files);

        if ($filter !== null && $filter !== '') {
            $files = array_values(array_filter(
                $files,
                static fn(string $f): bool => stripos(basename($f), $filter) !== false,
            ));
        }

        return $files;
    }
}
