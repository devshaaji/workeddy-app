<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;

final class ReportingSnapshotServiceTest extends TestCase
{
    public function testPilotSummaryAppliesRequestedFiltersAcrossQueries(): void
    {
        $queries = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(function (string $sql, array $params = []) use (&$queries) {
            $queries[] = ['sql' => $sql, 'params' => $params];

            return 1;
        });
        $connection->method('fetchAssociative')->willReturnCallback(function (string $sql, array $params = []) use (&$queries) {
            $queries[] = ['sql' => $sql, 'params' => $params];

            if (str_contains($sql, 'FROM supervisor_feedback')) {
                return [
                    'totalResponses' => 2,
                    'averageSeverity' => 4.0,
                    'averageFrequency' => 3.0,
                ];
            }

            return [
                'totalResponses' => 4,
                'anonymousResponses' => 1,
                'averageDiscomfort' => 3.5,
                'averagePain7Day' => 2.0,
                'averagePain30Day' => 4.0,
            ];
        });
        $connection->method('fetchAllAssociative')->willReturnCallback(function (string $sql, array $params = []) use (&$queries) {
            $queries[] = ['sql' => $sql, 'params' => $params];

            if (str_contains($sql, 'FROM validation_reviews')) {
                return [[
                    'id' => 1,
                    'uuid' => '11111111-1111-4111-8111-111111111111',
                    'organization_id' => 3,
                    'organization_uuid' => 'org-1',
                    'assessment_uuid' => 'assessment-1',
                    'assessment_version' => 'v1',
                    'reviewer_user_id' => 44,
                    'reviewer_name' => 'Dr One',
                    'reviewer_credentials' => 'CPE',
                    'review_round' => 1,
                    'score_json' => '{"raw":8}',
                    'risk_level' => 'High',
                    'body_regions_json' => '["back"]',
                    'risk_factors_json' => '["force"]',
                    'notes' => null,
                    'is_primary' => 1,
                    'is_final' => 1,
                    'submitted_at' => '2026-07-09 09:00:00',
                    'created_at' => '2026-07-09 09:00:00',
                    'updated_at' => '2026-07-09 09:00:00',
                ], [
                    'id' => 2,
                    'uuid' => '22222222-2222-4222-8222-222222222222',
                    'organization_id' => 3,
                    'organization_uuid' => 'org-1',
                    'assessment_uuid' => 'assessment-1',
                    'assessment_version' => 'v1',
                    'reviewer_user_id' => 45,
                    'reviewer_name' => 'Dr Two',
                    'reviewer_credentials' => 'PT',
                    'review_round' => 1,
                    'score_json' => '{"raw":8}',
                    'risk_level' => 'High',
                    'body_regions_json' => '["back"]',
                    'risk_factors_json' => '["force"]',
                    'notes' => null,
                    'is_primary' => 0,
                    'is_final' => 1,
                    'submitted_at' => '2026-07-09 09:05:00',
                    'created_at' => '2026-07-09 09:05:00',
                    'updated_at' => '2026-07-09 09:05:00',
                ]];
            }
            if (str_contains($sql, 'GROUP BY body_region')) {
                return [['bodyRegion' => 'back', 'responses' => 2, 'averageDiscomfort' => 4.5]];
            }
            if (str_contains($sql, 'GROUP BY wf.task_uuid')) {
                return [['taskUuid' => 'task-1', 'taskName' => 'Packing', 'responses' => 2, 'averageDiscomfort' => 4.0]];
            }
            if (str_contains($sql, 'FROM supervisor_feedback')) {
                return [['date' => '2026-07-09', 'responses' => 2, 'averageSeverity' => 4.0]];
            }

            return [['date' => '2026-07-09', 'responses' => 2, 'averageDiscomfort' => 4.0]];
        });

        $service = new ReportingSnapshotService($connection);
        $result = $service->pilotSummary('org-1', [
            'industry' => 'Manufacturing',
            'worksiteUuid' => 'site-1',
            'departmentUuid' => 'dept-1',
            'jobRoleUuid' => 'role-1',
            'bodyRegion' => 'back',
            'fromDate' => '2026-07-01',
            'toDate' => '2026-07-09',
            'riskLevel' => 'High',
        ]);

        self::assertSame('site-1', $result['filters']['worksiteUuid']);
        self::assertSame('back', $result['filters']['bodyRegion']);
        self::assertSame(1, $result['summary']['assessments']);
        self::assertNotEmpty($queries);

        $serialized = json_encode($queries, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('worksiteUuid', $serialized);
        self::assertStringContainsString('departmentUuid', $serialized);
        self::assertStringContainsString('jobRoleUuid', $serialized);
        self::assertStringContainsString('industry', $serialized);
        self::assertStringContainsString('bodyRegion', $serialized);
        self::assertStringContainsString('riskLevelPattern', $serialized);
        self::assertStringContainsString('assessment_body_regions abr', $serialized);
        self::assertStringContainsString('wf.body_region = :bodyRegion', $serialized);
        self::assertStringContainsString('validation_reviews', $serialized);
        self::assertStringContainsString('supervisor_feedback', $serialized);
    }
}
