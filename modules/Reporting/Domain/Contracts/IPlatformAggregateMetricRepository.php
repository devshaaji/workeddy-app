<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Domain\Contracts;

interface IPlatformAggregateMetricRepository
{
    /**
     * Appends a new dated snapshot row for a metric (never updates an
     * existing row) so that previously generated/cited figures remain
     * reproducible even after the metric is recomputed.
     *
     * @param mixed $value Will be JSON-encoded into value_json.
     */
    public function store(
        string $metricKey,
        string $metricName,
        mixed $value,
        ?string $industry,
        ?string $dateRangeStart,
        ?string $dateRangeEnd,
        string $generatedAt,
    ): void;

    /**
     * @return array{metricKey: string, metricName: string, value: mixed, industry: ?string,
     *     dateRangeStart: ?string, dateRangeEnd: ?string, generatedAt: string}|null
     */
    public function latest(string $metricKey, ?string $industry = null): ?array;

    /**
     * Latest row per distinct metric_key (industry-agnostic, i.e. industry IS NULL rows),
     * keyed by metric_key.
     *
     * @return array<string, array{metricKey: string, metricName: string, value: mixed,
     *     industry: ?string, dateRangeStart: ?string, dateRangeEnd: ?string, generatedAt: string}>
     */
    public function latestAll(): array;
}
