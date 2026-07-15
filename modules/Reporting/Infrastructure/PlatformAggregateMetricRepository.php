<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Infrastructure;

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Reporting\Domain\Contracts\IPlatformAggregateMetricRepository;

final class PlatformAggregateMetricRepository implements IPlatformAggregateMetricRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function store(
        string $metricKey,
        string $metricName,
        mixed $value,
        ?string $industry,
        ?string $dateRangeStart,
        ?string $dateRangeEnd,
        string $generatedAt,
    ): void {
        $this->connection->insert('platform_aggregate_metrics', [
            'metric_key' => $metricKey,
            'metric_name' => $metricName,
            'value_json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'industry' => $industry,
            'date_range_start' => $dateRangeStart,
            'date_range_end' => $dateRangeEnd,
            'generated_at' => $generatedAt,
        ]);
    }

    public function latest(string $metricKey, ?string $industry = null): ?array
    {
        if ($industry === null) {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM platform_aggregate_metrics WHERE metric_key = ? AND industry IS NULL ORDER BY generated_at DESC, id DESC LIMIT 1',
                [$metricKey],
            );
        } else {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM platform_aggregate_metrics WHERE metric_key = ? AND industry = ? ORDER BY generated_at DESC, id DESC LIMIT 1',
                [$metricKey, $industry],
            );
        }

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function latestAll(): array
    {
        // Latest row per metric_key (industry IS NULL rows only \u2014 the
        // dashboard-level, platform-wide figures). Portable across MySQL
        // versions without relying on window functions.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.* FROM platform_aggregate_metrics m
             INNER JOIN (
                 SELECT metric_key, MAX(id) AS max_id
                 FROM platform_aggregate_metrics
                 WHERE industry IS NULL
                 GROUP BY metric_key
             ) latest ON latest.metric_key = m.metric_key AND latest.max_id = m.id',
        );

        $result = [];
        foreach ($rows as $row) {
            $hydrated = $this->hydrate($row);
            $result[$hydrated['metricKey']] = $hydrated;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{metricKey: string, metricName: string, value: mixed, industry: ?string,
     *     dateRangeStart: ?string, dateRangeEnd: ?string, generatedAt: string}
     */
    private function hydrate(array $row): array
    {
        $decoded = json_decode((string) ($row['value_json'] ?? 'null'), true);

        return [
            'metricKey' => (string) ($row['metric_key'] ?? ''),
            'metricName' => (string) ($row['metric_name'] ?? ''),
            'value' => $decoded,
            'industry' => isset($row['industry']) ? (string) $row['industry'] : null,
            'dateRangeStart' => isset($row['date_range_start']) ? (string) $row['date_range_start'] : null,
            'dateRangeEnd' => isset($row['date_range_end']) ? (string) $row['date_range_end'] : null,
            'generatedAt' => (string) ($row['generated_at'] ?? ''),
        ];
    }
}
