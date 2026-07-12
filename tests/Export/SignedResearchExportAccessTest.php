<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Export;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Export\Application\IssueSignedResearchExportAccessUseCase;
use WorkEddy\Modules\Export\Application\ReadSignedResearchExportAccessUseCase;
use WorkEddy\Modules\Export\Authorization\ExportPermissions;
use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Domain\ResearchExport;
use WorkEddy\Modules\Export\Settings\ExportSettings;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\ForbiddenException;

final class SignedResearchExportAccessTest extends TestCase
{
    public function test_signed_export_access_requires_download_privilege(): void
    {
        $repo = new SignedAccessResearchExportRepository();
        $settings = new ExportSettings(new SettingsService([
            'export.allowed_formats' => ['csv'],
            'export.default_format' => 'csv',
            'export.signed_link_ttl_minutes' => 15,
            'export.max_export_rows' => 50000,
            'export.deidentification_profile' => 'research_default_v1',
        ]));

        $this->expectException(\RuntimeException::class);

        (new IssueSignedResearchExportAccessUseCase(
            $repo,
            $settings,
            new RecordingSignedResearchExportAuditService(),
            new MutableResearchExportClock('2026-07-09 10:00:00'),
            new AllowingSignedResearchExportPermissionService(),
            'test-secret',
        ))->execute(
            '11111111-1111-4111-8111-111111111111',
            new UserContext(
                userId: 44,
                organizationId: 3,
                organizationUuid: '22222222-2222-4222-8222-222222222222',
                roleType: 'staff',
                privileges: [],
            ),
        );
    }

    public function test_signed_export_access_logs_validates_and_streams_until_expiry(): void
    {
        $repo = new SignedAccessResearchExportRepository();
        $settings = new ExportSettings(new SettingsService([
            'export.allowed_formats' => ['csv'],
            'export.default_format' => 'csv',
            'export.signed_link_ttl_minutes' => 1,
            'export.max_export_rows' => 50000,
            'export.deidentification_profile' => 'research_default_v1',
        ]));
        $clock = new MutableResearchExportClock('2026-07-09 10:00:00');
        $audit = new RecordingSignedResearchExportAuditService();
        $permissions = new AllowingSignedResearchExportPermissionService();
        $actor = new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            roleType: 'staff',
            privileges: [ExportPermissions::DOWNLOAD],
        );

        $issued = (new IssueSignedResearchExportAccessUseCase(
            $repo,
            $settings,
            $audit,
            $clock,
            $permissions,
            'test-secret',
        ))->execute('11111111-1111-4111-8111-111111111111', $actor, 'download');

        self::assertStringContainsString('/api/v1/research-exports/signed-access/', $issued['signedUrl']);
        self::assertSame('export.research.signed_access_issued', $audit->records[0]['action']);

        $read = (new ReadSignedResearchExportAccessUseCase(
            $repo,
            new ReadableResearchExportStorageService(),
            $clock,
            $audit,
            'test-secret',
        ))->execute(substr((string) $issued['signedUrl'], strrpos((string) $issued['signedUrl'], '/') + 1));

        self::assertSame('text/csv', $read['mimeType']);
        self::assertSame('csv-bytes', $read['body']);
        self::assertSame('export.research.signed_access_streamed', $audit->records[1]['action']);

        $clock->now = '2026-07-09 10:02:00';
        $this->expectException(ForbiddenException::class);
        (new ReadSignedResearchExportAccessUseCase(
            $repo,
            new ReadableResearchExportStorageService(),
            $clock,
            $audit,
            'test-secret',
        ))->execute(substr((string) $issued['signedUrl'], strrpos((string) $issued['signedUrl'], '/') + 1));
    }
}

final class SignedAccessResearchExportRepository implements IResearchExportRepository
{
    private ResearchExport $export;

    public function __construct()
    {
        $this->export = new ResearchExport(
            id: 1,
            uuid: '11111111-1111-4111-8111-111111111111',
            organizationId: 3,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            dataset: 'assessments',
            format: 'csv',
            status: 'ready',
            filters: [],
            columnSchema: [],
            deidentificationProfile: 'research_default_v1',
            storageFileUuid: '33333333-3333-4333-8333-333333333333',
            rowCount: 1,
            generatedByUserId: 44,
            generatedAt: '2026-07-09 09:59:00',
            expiresAt: null,
        );
    }

    public function create(ResearchExport $export): int { $this->export = $export; return 1; }
    public function update(ResearchExport $export): void { $this->export = $export; }
    public function findByUuid(string $uuid): ?ResearchExport { return $uuid === $this->export->uuid ? $this->export : null; }
    public function findByStorageFileUuid(string $storageFileUuid): ?ResearchExport { return $storageFileUuid === $this->export->storageFileUuid ? $this->export : null; }
    public function listByOrganizationUuid(string $organizationUuid, int $limit = 20): array { return [$this->export]; }
    public function replaceCodeMaps(string $exportUuid, string $entityType, array $maps): void {}
    public function fetchAssessmentDataset(string $organizationUuid, array $filters, int $limit): array { return []; }
    public function fetchWorkerFeedbackDataset(string $organizationUuid, array $filters, int $limit): array { return []; }
    public function countAssessmentDataset(string $organizationUuid, array $filters): int { return 0; }
    public function countWorkerFeedbackDataset(string $organizationUuid, array $filters): int { return 0; }
}

final class MutableResearchExportClock implements IClock
{
    public function __construct(public string $now) {}
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable($this->now); }
}

final class RecordingSignedResearchExportAuditService implements IAuditService
{
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}

final class AllowingSignedResearchExportPermissionService implements \WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class ReadableResearchExportStorageService implements IStorageService
{
    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO { return null; }

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO
    {
        return new StoredFileDTO(
            null,
            $uuid,
            'local',
            'private',
            'active',
            'exports/research.csv',
            'export',
            '11111111-1111-4111-8111-111111111111',
            'csv',
            'research.csv',
            'text/csv',
            'csv',
            9,
        );
    }

    public function list(array $filters = []): array { return []; }
    public function count(array $filters = []): int { return 0; }
    public function summary(array $filters = []): array { return ['totalFiles' => 0, 'totalBytes' => 0, 'byCategory' => []]; }
    public function read(string $uuid): string { return 'csv-bytes'; }
    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO { return $this->findByUuid($uuid); }
    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO { return $this->findByUuid($uuid); }
    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO { return $this->findByUuid($uuid); }
    public function usageCount(string $uuid): int { return 0; }
}
