<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Domain\Contracts;

use WorkEddy\Modules\Reporting\Domain\NationalStatistic;

interface INationalStatisticRepository
{
    public function create(NationalStatistic $statistic): int;

    public function update(NationalStatistic $statistic): void;

    public function delete(string $uuid): void;

    public function findByUuid(string $uuid): ?NationalStatistic;

    /**
     * @return list<NationalStatistic>
     */
    public function listAll(bool $publishedOnly = false): array;

    /**
     * @return list<NationalStatistic>
     */
    public function listByCategory(string $category, bool $publishedOnly = true): array;
}
