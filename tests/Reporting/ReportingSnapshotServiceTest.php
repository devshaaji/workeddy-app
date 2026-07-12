<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Reporting;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;

final class ReportingSnapshotServiceTest extends TestCase
{
    public function test_dashboard_and_finance_snapshots_fail_closed_when_tables_are_missing(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $service = new ReportingSnapshotService($connection);

        self::assertSame([
            'customer_summary' => [
                'total_customers' => 0,
                'active_customers' => 0,
            ],
            'finance_summary' => [
                'income_total' => 0.0,
                'expense_total' => 0.0,
                'payroll_gross_total' => 0.0,
            ],
            'staff_summary' => [
                'active_employees' => 0,
            ],
        ], $service->dashboard());

        self::assertSame([
            'finance_summary' => [
                'income_total' => 0.0,
                'expense_total' => 0.0,
                'payroll_gross_total' => 0.0,
            ],
            'income_by_category' => [],
            'expense_by_category' => [],
            'payroll_periods' => [],
        ], $service->finance());

        self::assertSame([
            'staff_summary' => [
                'active_employees' => 0,
            ],
            'customer_summary' => [
                'total_customers' => 0,
                'active_customers' => 0,
            ],
        ], $service->operations());
    }

    public function test_dashboard_overview_executes_successfully(): void
    {
        $connection = \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        
        $connection->executeStatement('CREATE TABLE assessments (id INTEGER PRIMARY KEY, uuid TEXT, task_uuid TEXT, organization_id INTEGER, organization_uuid TEXT, status TEXT, created_at TEXT, final_score_json TEXT, initial_score_json TEXT)');
        $connection->executeStatement('CREATE TABLE tasks (id INTEGER PRIMARY KEY, uuid TEXT, worksite_id INTEGER, department_id INTEGER, job_role_id INTEGER)');
        $connection->executeStatement('CREATE TABLE comparison_reports (id INTEGER PRIMARY KEY, organization_uuid TEXT, baseline_assessment_uuid TEXT)');
        $connection->executeStatement('CREATE TABLE corrective_actions (id INTEGER PRIMARY KEY, organization_uuid TEXT, assessment_uuid TEXT, status TEXT)');
        $connection->executeStatement('CREATE TABLE worker_feedback (id INTEGER PRIMARY KEY, organization_uuid TEXT, created_at TEXT, worksite_uuid TEXT, department_uuid TEXT, job_role_uuid TEXT, body_region TEXT, discomfort_level REAL, pain_7_day_level REAL, pain_30_day_level REAL, anonymous_status INTEGER)');
        $connection->executeStatement('CREATE TABLE pilot_sites (id INTEGER PRIMARY KEY, organization_id INTEGER, organization_uuid TEXT, worksite_id INTEGER, worksite_uuid TEXT, industry TEXT, actual_worker_count INTEGER, deleted_at TEXT)');
        $connection->executeStatement('CREATE TABLE assessment_videos (id INTEGER PRIMARY KEY, assessment_id INTEGER)');
        $connection->executeStatement('CREATE TABLE assessment_body_regions (id INTEGER PRIMARY KEY, assessment_id INTEGER, region TEXT)');
        $connection->executeStatement('CREATE TABLE worksites (id INTEGER PRIMARY KEY, uuid TEXT)');
        $connection->executeStatement('CREATE TABLE departments (id INTEGER PRIMARY KEY, uuid TEXT)');
        $connection->executeStatement('CREATE TABLE job_roles (id INTEGER PRIMARY KEY, uuid TEXT)');

        $service = new ReportingSnapshotService($connection);
        
        $result = $service->dashboardOverview('some-org-uuid', [
            'industry' => 'Manufacturing',
            'worksiteUuid' => 'worksite-uuid',
            'departmentUuid' => 'dept-uuid',
            'jobRoleUuid' => 'role-uuid',
            'bodyRegion' => 'Back',
            'fromDate' => '2026-01-01 00:00:00',
            'toDate' => '2026-12-31 23:59:59',
            'riskLevel' => 'high',
        ]);

        self::assertIsArray($result);
        self::assertArrayHasKey('summary', $result);
        self::assertArrayHasKey('timeline', $result);
        self::assertSame('some-org-uuid', $result['organizationUuid']);
        
        $summary = $result['summary'];
        self::assertSame(0, $summary['worksites_enrolled']);
        self::assertSame(0, $summary['workers_participating']);
        self::assertSame(0, $summary['task_videos_uploaded']);
        self::assertSame(0, $summary['assessments']);
        self::assertSame(0, $summary['corrective_actions_total']);
    }
}
