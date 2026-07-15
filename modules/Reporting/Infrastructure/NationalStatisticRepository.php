<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Modules\Reporting\Domain\NationalStatistic;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;

final class NationalStatisticRepository implements INationalStatisticRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function create(NationalStatistic $statistic): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->insert('national_statistics', [
            'uuid' => $statistic->uuid !== '' ? $statistic->uuid : UuidSupport::generate(),
            'title' => $statistic->title,
            'value' => $statistic->value,
            'unit' => $statistic->unit,
            'category' => $statistic->category,
            'industry_relevance' => $statistic->industryRelevance,
            'source_name' => $statistic->sourceName,
            'source_year' => $statistic->sourceYear,
            'source_url' => $statistic->sourceUrl,
            'is_published' => $statistic->isPublished ? 1 : 0,
            'date_added' => $statistic->dateAdded,
            'created_by_user_id' => $statistic->createdByUserId,
            'updated_by_user_id' => $statistic->updatedByUserId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(NationalStatistic $statistic): void
    {
        $this->connection->update('national_statistics', [
            'title' => $statistic->title,
            'value' => $statistic->value,
            'unit' => $statistic->unit,
            'category' => $statistic->category,
            'industry_relevance' => $statistic->industryRelevance,
            'source_name' => $statistic->sourceName,
            'source_year' => $statistic->sourceYear,
            'source_url' => $statistic->sourceUrl,
            'is_published' => $statistic->isPublished ? 1 : 0,
            'updated_by_user_id' => $statistic->updatedByUserId,
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'uuid' => $statistic->uuid,
        ]);
    }

    public function delete(string $uuid): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->connection->update('national_statistics', [
            'deleted_at' => $now,
            'updated_at' => $now,
        ], [
            'uuid' => $uuid,
        ]);
    }

    public function findByUuid(string $uuid): ?NationalStatistic
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM national_statistics WHERE uuid = ? AND deleted_at IS NULL',
            [$uuid],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function listAll(bool $publishedOnly = false): array
    {
        $sql = 'SELECT * FROM national_statistics WHERE deleted_at IS NULL';
        if ($publishedOnly) {
            $sql .= ' AND is_published = 1';
        }
        $sql .= ' ORDER BY category ASC, source_year DESC, title ASC';

        $rows = $this->connection->fetchAllAssociative($sql);

        return array_map(fn(array $row): NationalStatistic => $this->hydrate($row), $rows);
    }

    public function listByCategory(string $category, bool $publishedOnly = true): array
    {
        $sql = 'SELECT * FROM national_statistics WHERE deleted_at IS NULL AND category = ?';
        if ($publishedOnly) {
            $sql .= ' AND is_published = 1';
        }
        $sql .= ' ORDER BY source_year DESC, title ASC';

        $rows = $this->connection->fetchAllAssociative($sql, [$category]);

        return array_map(fn(array $row): NationalStatistic => $this->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): NationalStatistic
    {
        return new NationalStatistic(
            id: isset($row['id']) ? (int) $row['id'] : null,
            uuid: (string) ($row['uuid'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            value: (string) ($row['value'] ?? ''),
            unit: isset($row['unit']) ? (string) $row['unit'] : null,
            category: (string) ($row['category'] ?? ''),
            industryRelevance: isset($row['industry_relevance']) ? (string) $row['industry_relevance'] : null,
            sourceName: (string) ($row['source_name'] ?? ''),
            sourceYear: (int) ($row['source_year'] ?? 0),
            sourceUrl: (string) ($row['source_url'] ?? ''),
            isPublished: (bool) ($row['is_published'] ?? true),
            dateAdded: (string) ($row['date_added'] ?? ''),
            createdByUserId: isset($row['created_by_user_id']) ? (int) $row['created_by_user_id'] : null,
            updatedByUserId: isset($row['updated_by_user_id']) ? (int) $row['updated_by_user_id'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? ''),
        );
    }
}
