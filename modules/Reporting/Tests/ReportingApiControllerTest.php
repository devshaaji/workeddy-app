<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Tests;

use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Reporting\Application\Services\ReportArtifactService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Application\IssueSignedReportAccessUseCase;
use WorkEddy\Modules\Reporting\Application\RegenerateReportArtifactUseCase;
use WorkEddy\Modules\Reporting\Application\ReadSignedReportAccessUseCase;
use WorkEddy\Modules\Reporting\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Reporting\Application\UseCases\GenerateCsv;
use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Reporting\Presentation\ReportingApiController;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsService;

final class ReportingApiControllerTest extends TestCase
{
    public function testDownloadDashboardPdfRedirectsToSignedUrl(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);

        $snapshots = new ReportingSnapshotService($connMock);

        $storageMock = $this->createMock(IStorageService::class);
        $dummyStoredFile = new StoredFileDTO(
            id: 1,
            uuid: 'e716c0b3-96b5-4b07-96ef-2ad6a8ab1bdf',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/dashboard_report.pdf',
            ownerType: 'reporting',
            ownerUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            fieldName: 'pdf',
            originalName: 'dashboard_report.pdf',
            mimeType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 1234,
            createdAt: '2026-07-07 09:00:00',
            updatedAt: '2026-07-07 09:00:00'
        );

        $storageMock->method('storeUploadedFile')->willReturn($dummyStoredFile);
        $storageMock->method('findByUuid')->willReturn($dummyStoredFile);
        $storageMock->method('read')->willReturn('dummy pdf bytes');

        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $artifactRepo->method('create')->willReturn(1);
        $artifactRepo->method('findByStorageFileUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '11111111-1111-4111-8111-111111111111',
            organizationUuid: null,
            reportType: 'dashboard',
            sourceUuid: null,
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'pdf',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'dashboard',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifactRepo->method('findByUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '11111111-1111-4111-8111-111111111111',
            organizationUuid: null,
            reportType: 'dashboard',
            sourceUuid: null,
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'pdf',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'dashboard',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $audit->expects($this->once())->method('record');
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);

        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);

        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $request = new Request(
            method: 'GET',
            uri: '/api/v1/reporting/dashboard/pdf',
            headers: [],
            query: [],
            body: [],
            routeParams: []
        );

        $response = $controller->downloadDashboardPdf($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringStartsWith('/api/v1/reporting/signed-access/', $response->getHeaders()['Location']);
    }

    public function testDownloadDashboardCsvRedirectsToSignedUrl(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);

        $snapshots = new ReportingSnapshotService($connMock);

        $storageMock = $this->createMock(IStorageService::class);
        $dummyStoredFile = new StoredFileDTO(
            id: 1,
            uuid: 'e716c0b3-96b5-4b07-96ef-2ad6a8ab1bdf',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/dashboard_report.csv',
            ownerType: 'reporting',
            ownerUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            fieldName: 'csv',
            originalName: 'dashboard_report.csv',
            mimeType: 'text/csv',
            extension: 'csv',
            sizeBytes: 1234,
            createdAt: '2026-07-07 09:00:00',
            updatedAt: '2026-07-07 09:00:00'
        );

        $storageMock->method('storeUploadedFile')->willReturn($dummyStoredFile);
        $storageMock->method('findByUuid')->willReturn($dummyStoredFile);
        $storageMock->method('read')->willReturn('dummy csv bytes');

        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $artifactRepo->method('create')->willReturn(1);
        $artifactRepo->method('findByStorageFileUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '22222222-2222-4222-8222-222222222222',
            organizationUuid: null,
            reportType: 'dashboard',
            sourceUuid: null,
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'csv',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'dashboard',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifactRepo->method('findByUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '22222222-2222-4222-8222-222222222222',
            organizationUuid: null,
            reportType: 'dashboard',
            sourceUuid: null,
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'csv',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'dashboard',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $audit->expects($this->once())->method('record');
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);

        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);

        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $request = new Request(
            method: 'GET',
            uri: '/api/v1/reporting/dashboard/csv',
            headers: [],
            query: [],
            body: [],
            routeParams: []
        );

        $response = $controller->downloadDashboardCsv($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringStartsWith('/api/v1/reporting/signed-access/', $response->getHeaders()['Location']);
    }

    public function testDownloadAssessmentPdfRedirectsToSignedUrl(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);

        $snapshots = new ReportingSnapshotService($connMock);

        $storageMock = $this->createMock(IStorageService::class);
        $dummyStoredFile = new StoredFileDTO(
            id: 1,
            uuid: 'e716c0b3-96b5-4b07-96ef-2ad6a8ab1bdf',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/assessment_report.pdf',
            ownerType: 'reporting',
            ownerUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            fieldName: 'pdf',
            originalName: 'assessment_report.pdf',
            mimeType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 1234,
            createdAt: '2026-07-07 09:00:00',
            updatedAt: '2026-07-07 09:00:00'
        );

        $storageMock->method('storeUploadedFile')->willReturn($dummyStoredFile);
        $storageMock->method('findByUuid')->willReturn($dummyStoredFile);
        $storageMock->method('read')->willReturn('dummy pdf bytes');

        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $artifactRepo->method('create')->willReturn(1);
        $artifactRepo->method('findByStorageFileUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '33333333-3333-4333-8333-333333333333',
            organizationUuid: 'org-uuid',
            reportType: 'assessment',
            sourceUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'pdf',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'assessment',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifactRepo->method('findByUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '33333333-3333-4333-8333-333333333333',
            organizationUuid: 'org-uuid',
            reportType: 'assessment',
            sourceUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'pdf',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'assessment',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $audit->expects($this->once())->method('record');
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);

        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);

        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $request = new Request(
            method: 'GET',
            uri: '/api/v1/reporting/assessment/d5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0/pdf',
            headers: [],
            query: [],
            body: [],
            routeParams: ['uuid' => 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0']
        );

        $response = $controller->downloadAssessmentPdf($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringStartsWith('/api/v1/reporting/signed-access/', $response->getHeaders()['Location']);
    }

    public function testDownloadAssessmentCsvRedirectsToSignedUrl(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);

        $snapshots = new ReportingSnapshotService($connMock);

        $storageMock = $this->createMock(IStorageService::class);
        $dummyStoredFile = new StoredFileDTO(
            id: 1,
            uuid: 'e716c0b3-96b5-4b07-96ef-2ad6a8ab1bdf',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/assessment_report.csv',
            ownerType: 'reporting',
            ownerUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            fieldName: 'csv',
            originalName: 'assessment_report.csv',
            mimeType: 'text/csv',
            extension: 'csv',
            sizeBytes: 1234,
            createdAt: '2026-07-07 09:00:00',
            updatedAt: '2026-07-07 09:00:00'
        );

        $storageMock->method('storeUploadedFile')->willReturn($dummyStoredFile);
        $storageMock->method('findByUuid')->willReturn($dummyStoredFile);
        $storageMock->method('read')->willReturn('dummy csv bytes');

        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $artifactRepo->method('create')->willReturn(1);
        $artifactRepo->method('findByStorageFileUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationUuid: 'org-uuid',
            reportType: 'assessment',
            sourceUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'csv',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'assessment',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifactRepo->method('findByUuid')->willReturn(new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '44444444-4444-4444-8444-444444444444',
            organizationUuid: 'org-uuid',
            reportType: 'assessment',
            sourceUuid: 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'csv',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'assessment',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        ));
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $audit->expects($this->once())->method('record');
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);

        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);

        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $request = new Request(
            method: 'GET',
            uri: '/api/v1/reporting/assessment/d5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0/csv',
            headers: [],
            query: [],
            body: [],
            routeParams: ['uuid' => 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0']
        );

        $response = $controller->downloadAssessmentCsv($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringStartsWith('/api/v1/reporting/signed-access/', $response->getHeaders()['Location']);
    }

    public function testGetAssessmentJson(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);

        $snapshots = new ReportingSnapshotService($connMock);

        $storageMock = $this->createMock(IStorageService::class);
        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $artifactRepo->method('create')->willReturn(1);
        $artifactRepo->method('listByReportSource')->willReturn([]);
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);

        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);

        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $request = new Request(
            method: 'GET',
            uri: '/api/v1/reporting/assessment/d5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0',
            headers: [],
            query: [],
            body: [],
            routeParams: ['uuid' => 'd5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0']
        );

        $response = $controller->assessment($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('d5c58a69-6d8d-4e94-9b5f-5ee202f5a6b0', $data['data']['uuid']);
    }

    public function testListArtifactsReturnsArtifactHistory(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);
        $snapshots = new ReportingSnapshotService($connMock);
        $storageMock = $this->createMock(IStorageService::class);
        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $artifactRepo->method('listByReportSource')->willReturn([
            new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
                id: 1,
                uuid: '55555555-5555-4555-8555-555555555555',
                organizationUuid: 'org-uuid',
                reportType: 'assessment',
                sourceUuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                previousArtifactUuid: null,
                regenerationReason: null,
                format: 'pdf',
                storageFileUuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                templateName: 'assessment',
                templateVersion: 'v1',
                snapshotHash: 'hash',
                generatedByUserId: 42,
                generatedAt: '2026-07-08 10:00:00',
            ),
        ]);
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);
        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);
        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $response = $controller->listArtifacts(new Request(
            method: 'GET',
            uri: '/api/v1/reporting/artifacts',
            headers: [],
            query: ['reportType' => 'assessment', 'sourceUuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'],
            body: [],
            routeParams: []
        ));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('assessment', $data['data']['reportType']);
        $this->assertCount(1, $data['data']['items']);
    }

    public function testReadSignedAccessStreamsArtifact(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);
        $snapshots = new ReportingSnapshotService($connMock);
        $dummyStoredFile = new StoredFileDTO(
            id: 1,
            uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/assessment_report.pdf',
            ownerType: 'reporting',
            ownerUuid: '55555555-5555-4555-8555-555555555555',
            fieldName: 'pdf',
            originalName: 'assessment_report.pdf',
            mimeType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 1234,
            createdAt: '2026-07-07 09:00:00',
            updatedAt: '2026-07-07 09:00:00'
        );
        $storageMock = $this->createMock(IStorageService::class);
        $storageMock->method('findByUuid')->willReturn($dummyStoredFile);
        $storageMock->method('read')->willReturn('signed pdf bytes');
        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
            'reporting.template_version' => 'v-test',
            'reporting.methodology_note' => 'Method note',
            'reporting.limitations_note' => 'Limit note',
            'reporting.privacy_note' => 'Privacy note',
            'reporting.download_link_ttl_minutes' => 15,
            'reporting.default_revenue_window_days' => 30,
            'reporting.include_expired_customers' => true,
        ]);
        $settings = new ReportingSettings($globalSettings);
        $artifact = new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '55555555-5555-4555-8555-555555555555',
            organizationUuid: 'org-uuid',
            reportType: 'assessment',
            sourceUuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'pdf',
            storageFileUuid: $dummyStoredFile->uuid,
            templateName: 'assessment',
            templateVersion: 'v1',
            snapshotHash: 'hash',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:00:00',
        );
        $artifactRepo = $this->createMock(IReportArtifactRepository::class);
        $artifactRepo->method('findByUuid')->willReturn($artifact);
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $audit->expects($this->exactly(2))->method('record');
        $clock = new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00'));
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, $clock);
        $signed = $issueSigned->execute($artifact->uuid, new UserContext(userId: 42));
        $token = substr((string) $signed['signedUrl'], strrpos((string) $signed['signedUrl'], '/') + 1);
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, $clock, $audit);
        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);
        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $response = $controller->readSignedAccess(new Request(
            method: 'GET',
            uri: '/api/v1/reporting/signed-access/' . $token,
            headers: [],
            query: [],
            body: [],
            routeParams: ['token' => $token]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaders()['Content-Type']);
        $this->assertSame('signed pdf bytes', $response->getBody());
    }

    public function testVersionChainReturnsLineage(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);
        $snapshots = new ReportingSnapshotService($connMock);
        $storageMock = $this->createMock(IStorageService::class);
        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $artifactRepo->method('listVersionChain')->willReturn([
            new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
                id: 2,
                uuid: '66666666-6666-4666-8666-666666666666',
                organizationUuid: 'org-uuid',
                reportType: 'assessment',
                sourceUuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                previousArtifactUuid: '55555555-5555-4555-8555-555555555555',
                regenerationReason: 'new data',
                format: 'pdf',
                storageFileUuid: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
                templateName: 'assessment',
                templateVersion: 'v2',
                snapshotHash: 'hash-2',
                generatedByUserId: 42,
                generatedAt: '2026-07-08 11:00:00',
            ),
        ]);
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);
        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);
        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $response = $controller->versionChain(new Request(
            method: 'GET',
            uri: '/api/v1/reporting/artifacts/66666666-6666-4666-8666-666666666666/versions',
            headers: [],
            query: [],
            body: [],
            routeParams: ['artifactUuid' => '66666666-6666-4666-8666-666666666666']
        ));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('66666666-6666-4666-8666-666666666666', $data['data']['artifactUuid']);
        $this->assertSame('55555555-5555-4555-8555-555555555555', $data['data']['items'][0]['previousArtifactUuid']);
    }

    public function testRegenerateArtifactReturnsNewArtifactAndSignedAccess(): void
    {
        $connMock = $this->createMock(Connection::class);
        $connMock->method('fetchOne')->willReturn(0);
        $snapshots = new ReportingSnapshotService($connMock);
        $storageMock = $this->createMock(IStorageService::class);
        $globalSettings = new SettingsService([
            'billing.org_name' => 'WorkEddy Corp',
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
        $previousArtifact = new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 1,
            uuid: '55555555-5555-4555-8555-555555555555',
            organizationUuid: 'org-uuid',
            reportType: 'dashboard',
            sourceUuid: null,
            previousArtifactUuid: null,
            regenerationReason: null,
            format: 'pdf',
            storageFileUuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            templateName: 'dashboard',
            templateVersion: 'v1',
            snapshotHash: 'hash-1',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 10:55:00',
        );
        $newArtifact = new \WorkEddy\Modules\Reporting\Domain\ReportArtifact(
            id: 2,
            uuid: '77777777-7777-4777-8777-777777777777',
            organizationUuid: 'org-uuid',
            reportType: 'dashboard',
            sourceUuid: null,
            previousArtifactUuid: '55555555-5555-4555-8555-555555555555',
            regenerationReason: 'fresh proof',
            format: 'pdf',
            storageFileUuid: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            templateName: 'dashboard',
            templateVersion: 'v2',
            snapshotHash: 'hash-3',
            generatedByUserId: 42,
            generatedAt: '2026-07-08 11:05:00',
        );
        $artifactRepo->method('findByUuid')->willReturnCallback(static function (string $uuid) use ($previousArtifact, $newArtifact) {
            return match ($uuid) {
                '55555555-5555-4555-8555-555555555555' => $previousArtifact,
                '77777777-7777-4777-8777-777777777777' => $newArtifact,
                default => null,
            };
        });
        $artifactRepo->method('findByStorageFileUuid')->willReturnCallback(static function (string $uuid) use ($newArtifact) {
            return $uuid === $newArtifact->storageFileUuid ? $newArtifact : null;
        });
        $artifactRepo->method('create')->willReturn(1);
        $artifacts = new ReportArtifactService($artifactRepo);
        $session = $this->createMock(ISessionService::class);
        $session->method('getUserContext')->willReturn(new UserContext(userId: 42));
        $audit = $this->createMock(IAuditService::class);
        $audit->expects($this->exactly(2))->method('record');
        $issueSigned = new IssueSignedReportAccessUseCase($artifactRepo, $settings, $audit, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')));
        $readSigned = new ReadSignedReportAccessUseCase($artifactRepo, $storageMock, new \WorkEddy\Platform\Clock\FrozenClock(new \DateTimeImmutable('2026-07-08 10:00:00')), $audit);
        $dummyStoredFile = new StoredFileDTO(
            id: 3,
            uuid: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/dashboard_report_regenerated.pdf',
            ownerType: 'reporting',
            ownerUuid: $newArtifact->uuid,
            fieldName: 'pdf',
            originalName: 'dashboard_report_regenerated.pdf',
            mimeType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 1234,
            createdAt: '2026-07-08 11:05:00',
            updatedAt: '2026-07-08 11:05:00'
        );
        $storageMock->method('storeUploadedFile')->willReturn($dummyStoredFile);
        $generatePdf = new GeneratePdf($snapshots, $artifacts, $storageMock, $settings, $session, $globalSettings);
        $generateCsv = new GenerateCsv($snapshots, $artifacts, $storageMock, $settings, $session);
        $regenerate = new RegenerateReportArtifactUseCase($artifactRepo, $generatePdf, $generateCsv, $audit);
        $controller = new ReportingApiController($snapshots, $generatePdf, $generateCsv, $regenerate, $artifactRepo, $issueSigned, $readSigned, $storageMock, $session, $audit);

        $response = $controller->regenerateArtifact(new Request(
            method: 'POST',
            uri: '/api/v1/reporting/artifacts/55555555-5555-4555-8555-555555555555/regenerate',
            headers: [],
            query: [],
            body: [],
            json: ['reason' => 'fresh proof', 'format' => 'pdf'],
            routeParams: ['artifactUuid' => '55555555-5555-4555-8555-555555555555']
        ));

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('77777777-7777-4777-8777-777777777777', $data['data']['artifact']['uuid']);
        $this->assertStringStartsWith('/api/v1/reporting/signed-access/', $data['data']['signedAccess']['signedUrl']);
    }
}
