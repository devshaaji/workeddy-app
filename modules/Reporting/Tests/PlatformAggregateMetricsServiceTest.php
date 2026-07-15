<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Reporting\Application\Services\PlatformAggregateMetricsService;
use WorkEddy\Modules\Reporting\Domain\Contracts\IPlatformAggregateMetricRepository;
use WorkEddy\Platform\Clock\FrozenClock;

final class PlatformAggregateMetricsServiceTest extends TestCase
{
    public function testRefreshParsesAssessmentScoreJsonInsteadOfStringMatching(): void
    {
        $stores = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql): float|int {
            return match (true) {
                str_contains($sql, 'COUNT(DISTINCT LOWER(TRIM(industry)))') => 3,
                str_contains($sql, 'COUNT(DISTINCT t.worksite_id)') => 7,
                str_contains($sql, 'AVG(risk_reduction_percent)') => 18.5,
                default => 0,
            };
        });
        $connection->method('fetchAllAssociative')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, 'FROM assessments a') && str_contains($sql, 'INNER JOIN tasks t')) {
                return [
                    [
                        'task_uuid' => 'task-1',
                        'task_name' => 'Manual pallet lift',
                        'initial_score_json' => '{"risk_level":"High","risk_category":"high"}',
                        'final_score_json' => null,
                    ],
                    [
                        'task_uuid' => 'task-2',
                        'task_name' => 'Picking at low shelf',
                        'initial_score_json' => '{"riskLevel":"Medium","riskCategory":"medium"}',
                        'final_score_json' => '{"risk_level":"Very High","risk_category":"very_high"}',
                    ],
                    [
                        'task_uuid' => 'task-3',
                        'task_name' => 'Desk task',
                        'initial_score_json' => '{"risk_level":"Low","risk_category":"low"}',
                        'final_score_json' => null,
                    ],
                ];
            }

            if (str_contains($sql, 'FROM assessment_body_regions abr')) {
                return [['region' => 'back', 'response_count' => 9]];
            }

            if (str_contains($sql, 'FROM corrective_actions')) {
                return [['status' => 'open', 'action_count' => 4]];
            }

            if (str_contains($sql, 'FROM worker_feedback')) {
                return [['month' => '2026-07', 'responses' => 5, 'avg_discomfort' => 3.4]];
            }

            return [];
        });

        $repository = new class($stores) implements IPlatformAggregateMetricRepository {
            /** @var array<string, mixed> */
            private array $stores;

            public function __construct(array &$stores)
            {
                $this->stores = &$stores;
            }

            public function store(string $metricKey, string $metricName, mixed $value, ?string $industry, ?string $dateRangeStart, ?string $dateRangeEnd, string $generatedAt): void
            {
                $this->stores[$metricKey] = $value;
            }

            public function latest(string $metricKey, ?string $industry = null): ?array
            {
                return null;
            }

            public function latestAll(): array
            {
                return [];
            }
        };

        $service = new PlatformAggregateMetricsService(
            $connection,
            $repository,
            new FrozenClock(new \DateTimeImmutable('2026-07-15 10:00:00')),
        );

        $service->refresh();

        self::assertSame(3, $stores['industries_represented']);
        self::assertSame(2, $stores['high_risk_tasks_identified']);
        self::assertSame([
            ['task' => 'Manual pallet lift', 'count' => 1],
            ['task' => 'Picking at low shelf', 'count' => 1],
        ], $stores['common_high_strain_tasks']);
        self::assertSame([
            ['status' => 'open', 'label' => 'Open', 'count' => 4],
        ], $stores['common_corrective_actions']);
    }
}
