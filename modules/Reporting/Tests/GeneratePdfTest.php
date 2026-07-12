<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Tests;

use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Reporting\Application\Services\ReportArtifactService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;

final class GeneratePdfTest extends TestCase
{
    public function testGenerateFinancePdfUploadsAndReturnsUuid(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'finance_income_records')) {
                return 1000.0;
            }
            if (str_contains($sql, 'finance_expense_records')) {
                return 500.0;
            }
            if (str_contains($sql, 'finance_payroll_summaries')) {
                return 200.0;
            }
            return 0;
        });

        $connMock->method('fetchAllAssociative')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'finance_income_records')) {
                return [['category' => 'Sales', 'total' => 1000.0]];
            }
            if (str_contains($sql, 'finance_expense_records')) {
                return [['category' => 'Rent', 'total' => 500.0]];
            }
            if (str_contains($sql, 'finance_payroll_summaries')) {
                return [['period_key' => '2026-06', 'gross_amount' => 200.0, 'net_amount' => 150.0, 'employee_count' => 5]];
            }
            return [];
        });

        $snapshots = new ReportingSnapshotService($connMock);

        $storageMock = $this->createMock(IStorageService::class);
        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Test Org',
            'billing.org_address' => '123 Test St',
            'billing.org_phone' => '555-0199',
            'billing.org_email' => 'test@workeddy.local',
            'billing.org_tax_id' => 'XX-XXXXXX',
            'reporting.template_version' => 'v-test',
            'reporting.methodology_note' => 'Method note',
            'reporting.limitations_note' => 'Limit note',
            'reporting.privacy_note' => 'Privacy note',
            'reporting.download_link_ttl_minutes' => 15,
            'reporting.default_revenue_window_days' => 30,
            'reporting.include_expired_customers' => true,
        ]);
        $settings = new ReportingSettings($globalSettings);
        $artifactRepo = $this->createMock(IReportArtifactRepository::class);
        $artifactRepo->expects($this->once())->method('create')->willReturn(1);
        $artifactService = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(null);

        $dummyStoredFile = new StoredFileDTO(
            id: 1,
            uuid: 'f827d0b3-96b5-4b07-96ef-2ad6a8ab1bdf',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/finance_report.pdf',
            ownerType: 'reporting',
            ownerUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            fieldName: 'pdf',
            originalName: 'finance_report.pdf',
            mimeType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 1234,
            createdAt: '2026-07-07 09:00:00',
            updatedAt: '2026-07-07 09:00:00'
        );

        $storageMock->expects($this->once())
            ->method('storeUploadedFile')
            ->with($this->callback(function (StoreUploadedFileRequest $req) {
                return $req->ownerType === 'reporting' && $req->fieldName === 'pdf';
            }))
            ->willReturn($dummyStoredFile);

        $useCase = new GeneratePdf($snapshots, $artifactService, $storageMock, $settings, $session, $globalSettings);
        $uuid = $useCase->generateFinancePdf();

        $this->assertSame('f827d0b3-96b5-4b07-96ef-2ad6a8ab1bdf', $uuid);
    }

    public function testGeneratePilotSummaryPdfUploadsAndReturnsUuid(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturnCallback(function (string $sql) {
            return match (true) {
                str_contains($sql, 'FROM pilot_sites') && str_contains($sql, 'SUM(ps.actual_worker_count)') => 18,
                str_contains($sql, 'FROM pilot_sites') => 2,
                str_contains($sql, 'FROM assessments') && str_contains($sql, "status IN ('reviewed','locked')") => 3,
                str_contains($sql, 'FROM assessments') && str_contains($sql, 'is_baseline = 1') => 2,
                str_contains($sql, 'FROM assessments') => 4,
                str_contains($sql, 'FROM comparison_reports') => 1,
                str_contains($sql, "FROM corrective_actions") && str_contains($sql, "status IN ('completed','verified')") => 2,
                str_contains($sql, 'FROM corrective_actions') => 3,
                default => 0,
            };
        });
        $connMock->method('fetchAssociative')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'FROM supervisor_feedback')) {
                return [
                    'totalResponses' => 3,
                    'averageSeverity' => 4.5,
                    'averageFrequency' => 3.0,
                ];
            }

            return [
                'totalResponses' => 6,
                'anonymousResponses' => 2,
                'averageDiscomfort' => 3.5,
                'averagePain7Day' => 2.0,
                'averagePain30Day' => 4.0,
            ];
        });
        $connMock->method('fetchAllAssociative')->willReturnCallback(function (string $sql) {
            if (str_contains($sql, 'FROM validation_reviews')) {
                return [[
                    'id' => 1,
                    'uuid' => '11111111-1111-4111-8111-111111111111',
                    'organization_id' => 3,
                    'organization_uuid' => 'org-uuid',
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
                    'submitted_at' => '2026-07-08 09:00:00',
                    'created_at' => '2026-07-08 09:00:00',
                    'updated_at' => '2026-07-08 09:00:00',
                ], [
                    'id' => 2,
                    'uuid' => '22222222-2222-4222-8222-222222222222',
                    'organization_id' => 3,
                    'organization_uuid' => 'org-uuid',
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
                    'submitted_at' => '2026-07-08 09:05:00',
                    'created_at' => '2026-07-08 09:05:00',
                    'updated_at' => '2026-07-08 09:05:00',
                ]];
            }
            if (str_contains($sql, 'GROUP BY body_region')) {
                return [['bodyRegion' => 'back', 'responses' => 4, 'averageDiscomfort' => 4.25]];
            }
            if (str_contains($sql, 'GROUP BY wf.task_uuid')) {
                return [['taskUuid' => 'task-1', 'taskName' => 'Packing', 'responses' => 3, 'averageDiscomfort' => 3.75]];
            }
            if (str_contains($sql, 'FROM supervisor_feedback')) {
                return [['date' => '2026-07-08', 'responses' => 2, 'averageSeverity' => 4.5]];
            }

            return [['date' => '2026-07-08', 'responses' => 2, 'averageDiscomfort' => 3.5]];
        });

        $snapshots = new ReportingSnapshotService($connMock);
        $storageMock = $this->createMock(IStorageService::class);
        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Test Org',
            'billing.org_address' => '123 Test St',
            'billing.org_phone' => '555-0199',
            'billing.org_email' => 'test@workeddy.local',
            'billing.org_tax_id' => 'XX-XXXXXX',
            'reporting.template_version' => 'v-test',
            'reporting.methodology_note' => 'Method note',
            'reporting.limitations_note' => 'Limit note',
            'reporting.privacy_note' => 'Privacy note',
            'reporting.download_link_ttl_minutes' => 15,
            'reporting.default_revenue_window_days' => 30,
            'reporting.include_expired_customers' => true,
        ]);
        $settings = new ReportingSettings($globalSettings);
        $artifactRepo = $this->createMock(IReportArtifactRepository::class);
        $artifactRepo->expects($this->once())->method('create')->willReturn(1);
        $artifactService = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(null);
        $dummyStoredFile = new StoredFileDTO(
            id: 2,
            uuid: 'f927d0b3-96b5-4b07-96ef-2ad6a8ab1bdf',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/pilot_summary_report.pdf',
            ownerType: 'reporting',
            ownerUuid: 'org-uuid',
            fieldName: 'pdf',
            originalName: 'pilot_summary_report.pdf',
            mimeType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 1234,
            createdAt: '2026-07-07 09:00:00',
            updatedAt: '2026-07-07 09:00:00'
        );
        $storageMock->expects($this->once())
            ->method('storeUploadedFile')
            ->with($this->callback(function (StoreUploadedFileRequest $req) {
                return $req->ownerType === 'reporting' && $req->fieldName === 'pdf';
            }))
            ->willReturn($dummyStoredFile);

        $useCase = new GeneratePdf($snapshots, $artifactService, $storageMock, $settings, $session, $globalSettings);
        $uuid = $useCase->generatePilotSummaryPdf('org-uuid');

        $this->assertSame('f927d0b3-96b5-4b07-96ef-2ad6a8ab1bdf', $uuid);
    }
}
