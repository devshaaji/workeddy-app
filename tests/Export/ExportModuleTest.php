<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Export;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Export\Application\Services\ResearchExportDeidentificationService;
use WorkEddy\Modules\Export\Application\Services\ResearchExportFileWriter;
use WorkEddy\Modules\Export\Application\Support\ResearchExportColumnCatalog;
use WorkEddy\Modules\Export\Application\UseCases\GenerateResearchExportUseCase;
use WorkEddy\Modules\Export\Presentation\ExportPageData;
use WorkEddy\Modules\Export\Authorization\ExportPermissions;
use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Domain\ResearchExport;
use WorkEddy\Modules\Export\Infrastructure\ResearchExportRepository;
use WorkEddy\Modules\Export\Settings\ExportSettings;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Infrastructure\StorageRepository;
use WorkEddy\Modules\Storage\Infrastructure\StorageService;
use WorkEddy\Modules\Storage\Settings\StorageSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Schema\CanonicalSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Export\ExportSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Storage\StorageSchemaBuilder;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\ModuleSettings;
use WorkEddy\Platform\Settings\SettingsService;

final class ExportModuleTest extends TestCase
{
    public function test_service_provider_exposes_settings_permissions_routes_and_view(): void
    {
        $provider = new \WorkEddy\Modules\Export\ServiceProvider();

        self::assertSame('export', $provider->getName());
        self::assertNotNull($provider->getSettingsProvider());
        self::assertSame('export', $provider->getSettingsProvider()?->getModuleName());
        self::assertNotSame([], $provider->getSettingsProvider()?->getDefinitions());
        self::assertNotNull($provider->getPermissionDefinitionProvider());
        self::assertSame('export', $provider->getPermissionDefinitionProvider()?->module());
        self::assertTrue(is_subclass_of(ExportSettings::class, ModuleSettings::class));
        self::assertFileExists((string) $provider->getRouteFile());
        self::assertFileExists(dirname((string) $provider->getRouteFile()) . '/Views/Index/index.php');
    }

    public function test_bootstrap_and_canonical_schema_register_export_module(): void
    {
        $modules = require __DIR__ . '/../../bootstrap/modules.php';

        self::assertContains(\WorkEddy\Modules\Export\ServiceProvider::class, $modules);

        $tables = (new CanonicalSchemaBuilder())->tables();
        self::assertContains('research_exports', $tables);
        self::assertContains('research_export_code_maps', $tables);
    }

    public function test_repository_create_persists_into_real_export_schema(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        (new ExportSchemaBuilder())->build($schema);

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $repository = new ResearchExportRepository($connection);
        $repository->create(new ResearchExport(
            id: null,
            uuid: '11111111-1111-4111-8111-111111111111',
            organizationId: 3,
            organizationUuid: '22222222-2222-4222-8222-222222222222',
            dataset: 'assessments',
            format: 'csv',
            status: 'pending',
            filters: ['fromDate' => '2026-07-01'],
            columnSchema: [],
            deidentificationProfile: 'research_default_v1',
            storageFileUuid: null,
            rowCount: null,
            generatedByUserId: 44,
            generatedAt: null,
            expiresAt: null,
        ));

        $saved = $repository->findByUuid('11111111-1111-4111-8111-111111111111');

        self::assertNotNull($saved);
        self::assertSame('assessments', $saved?->dataset);
    }

    public function test_repository_replace_code_maps_persists_created_at_in_real_schema(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        (new ExportSchemaBuilder())->build($schema);

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $repository = new ResearchExportRepository($connection);
        $repository->replaceCodeMaps('11111111-1111-4111-8111-111111111111', 'organization', [
            '22222222-2222-4222-8222-222222222222' => 'ORG001',
        ]);

        $row = $connection->fetchAssociative('SELECT * FROM research_export_code_maps LIMIT 1');

        self::assertIsArray($row);
        self::assertSame('11111111-1111-4111-8111-111111111111', $row['export_uuid'] ?? null);
        self::assertSame('organization', $row['entity_type'] ?? null);
        self::assertSame('22222222-2222-4222-8222-222222222222', $row['entity_uuid'] ?? null);
        self::assertSame('ORG001', $row['export_code'] ?? null);
        self::assertNotEmpty($row['created_at'] ?? null);
    }

    public function test_generate_research_export_deidentifies_rows_and_registers_storage(): void
    {
        $repository = new InMemoryResearchExportRepository();
        $storage = new RecordingResearchExportStorageService();
        $audit = new RecordingResearchExportAuditService();
        $permissions = new AllowingResearchExportPermissionService();
        $settings = new ExportSettings(new SettingsService([
            'export.allowed_formats' => ['csv', 'xlsx'],
            'export.default_format' => 'csv',
            'export.signed_link_ttl_minutes' => 15,
            'export.max_export_rows' => 50000,
            'export.deidentification_profile' => 'research_default_v1',
        ]));

        $useCase = new GenerateResearchExportUseCase(
            $repository,
            new ResearchExportDeidentificationService(new ResearchExportColumnCatalog()),
            new ResearchExportFileWriter(),
            $storage,
            $permissions,
            $audit,
            $settings,
        );

        $export = $useCase->execute(
            'assessments',
            'csv',
            new UserContext(
                userId: 44,
                organizationId: 3,
                organizationUuid: '11111111-1111-4111-8111-111111111111',
                roleType: 'staff',
                privileges: [ExportPermissions::GENERATE],
            ),
            ['fromDate' => '2026-07-01'],
        );

        self::assertSame('ready', $export->status);
        self::assertSame('stored-export-1', $export->storageFileUuid);
        self::assertSame(1, $export->rowCount);
        self::assertArrayHasKey('organization', $repository->codeMaps);
        self::assertStringContainsString('ORG001', $storage->storedBody);
        self::assertStringNotContainsString('11111111-1111-4111-8111-111111111111', $storage->storedBody);
        self::assertSame('export.research.generated', $audit->records[0]['action']);
    }

    public function test_generate_research_export_bypasses_generic_storage_upload_type_restrictions(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2));
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        (new StorageSchemaBuilder())->build($schema);
        (new \WorkEddy\Platform\Schema\Modules\IAM\IamSchemaBuilder())->build($schema);

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $repository = new InMemoryResearchExportRepository();
        $audit = new RecordingResearchExportAuditService();
        $permissions = new AllowingResearchExportPermissionService();
        $settings = new ExportSettings(new SettingsService([
            'export.allowed_formats' => ['csv', 'xlsx'],
            'export.default_format' => 'csv',
            'export.signed_link_ttl_minutes' => 15,
            'export.max_export_rows' => 50000,
            'export.deidentification_profile' => 'research_default_v1',
        ]));
        $privateRoot = 'storage/app/test-private-exports-' . bin2hex(random_bytes(4));
        $storage = new StorageService(
            new StorageRepository($connection),
            new StorageSettings(new SettingsService([
                'storage.default_disk' => 'local',
                'storage.default_visibility' => 'private',
                'storage.local_private_root' => $privateRoot,
                'storage.max_upload_bytes' => 5242880,
                'storage.allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'storage.allowed_mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                ],
            ])),
            $audit,
        );

        try {
            $useCase = new GenerateResearchExportUseCase(
                $repository,
                new ResearchExportDeidentificationService(new ResearchExportColumnCatalog()),
                new ResearchExportFileWriter(),
                $storage,
                $permissions,
                $audit,
                $settings,
            );

            $export = $useCase->execute(
                'assessments',
                'csv',
                new UserContext(
                    userId: 44,
                    organizationId: 3,
                    organizationUuid: '11111111-1111-4111-8111-111111111111',
                    roleType: 'staff',
                    privileges: [ExportPermissions::GENERATE],
                ),
                ['fromDate' => '2026-07-01'],
            );

            self::assertSame('ready', $export->status);
            self::assertNotNull($export->storageFileUuid);
            $stored = $connection->fetchAssociative('SELECT * FROM uploads WHERE uuid = ?', [$export->storageFileUuid]);
            self::assertNotFalse($stored);
            self::assertSame('csv', $stored['extension'] ?? null);
            self::assertSame('text/csv', $stored['mime_type'] ?? null);
        } finally {
            $absoluteRoot = dirname(__DIR__, 2) . '/' . $privateRoot;
            if (is_dir($absoluteRoot)) {
                $this->deleteDirectory($absoluteRoot);
            }
        }
    }

    public function test_page_data_exposes_summary_and_page_script_context(): void
    {
        $repository = new InMemoryResearchExportRepository();
        $repository->exports['exp-1'] = new ResearchExport(
            id: 1,
            uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            dataset: 'assessments',
            format: 'csv',
            status: 'ready',
            filters: [],
            columnSchema: [],
            deidentificationProfile: 'research_default_v1',
            storageFileUuid: 'file-1',
            rowCount: 15,
            generatedByUserId: 44,
            generatedAt: '2026-07-09 10:00:00',
            expiresAt: null,
        );
        $repository->exports['exp-2'] = new ResearchExport(
            id: 2,
            uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            dataset: 'worker_feedback',
            format: 'xlsx',
            status: 'pending',
            filters: [],
            columnSchema: [],
            deidentificationProfile: 'research_default_v1',
            storageFileUuid: null,
            rowCount: 8,
            generatedByUserId: 44,
            generatedAt: '2026-07-09 09:30:00',
            expiresAt: null,
        );
        $settings = new ExportSettings(new SettingsService([
            'export.allowed_formats' => ['csv', 'xlsx'],
            'export.default_format' => 'csv',
            'export.signed_link_ttl_minutes' => 15,
            'export.max_export_rows' => 50000,
            'export.deidentification_profile' => 'research_default_v1',
        ]));

        $data = (new ExportPageData($settings, $repository))->index(new UserContext(
            userId: 44,
            organizationId: 3,
            organizationUuid: '11111111-1111-4111-8111-111111111111',
            roleType: 'staff',
            privileges: [],
        ));

        self::assertSame(['js/modules/export.js'], $data['pageScripts']);
        self::assertSame(2, $data['summary']['recentExportCount']);
        self::assertSame(1, $data['summary']['readyExportCount']);
        self::assertSame(23, $data['summary']['totalRows']);
        self::assertSame(15, $data['summary']['signedLinkTtlMinutes']);
        self::assertSame('2026-07-09 10:00:00', $data['summary']['latestGeneratedAt']);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->deleteDirectory($child);
                continue;
            }

            @unlink($child);
        }

        @rmdir($path);
    }
}

final class InMemoryResearchExportRepository implements IResearchExportRepository
{
    /** @var array<string, ResearchExport> */
    public array $exports = [];

    /** @var array<string, array<string, string>> */
    public array $codeMaps = [];

    public function create(ResearchExport $export): int
    {
        $this->exports[$export->uuid] = $export;

        return 1;
    }

    public function update(ResearchExport $export): void
    {
        $this->exports[$export->uuid] = $export;
    }

    public function findByUuid(string $uuid): ?ResearchExport
    {
        return $this->exports[$uuid] ?? null;
    }

    public function findByStorageFileUuid(string $storageFileUuid): ?ResearchExport
    {
        foreach ($this->exports as $export) {
            if ($export->storageFileUuid === $storageFileUuid) {
                return $export;
            }
        }

        return null;
    }

    public function listByOrganizationUuid(string $organizationUuid, int $limit = 20): array
    {
        return array_slice(array_values(array_filter(
            $this->exports,
            static fn(ResearchExport $export): bool => $export->organizationUuid === $organizationUuid,
        )), 0, $limit);
    }

    public function replaceCodeMaps(string $exportUuid, string $entityType, array $maps): void
    {
        $this->codeMaps[$entityType] = $maps;
    }

    public function fetchAssessmentDataset(string $organizationUuid, array $filters, int $limit): array
    {
        return [[
            'assessment_uuid' => '33333333-3333-4333-8333-333333333333',
            'organization_uuid' => $organizationUuid,
            'task_uuid' => '44444444-4444-4444-8444-444444444444',
            'model' => 'reba',
            'initial_score_json' => '{"raw_score":8,"risk_level":"high"}',
            'final_score_json' => '{"raw_score":6,"risk_level":"medium"}',
            'status' => 'reviewed',
            'is_baseline' => 1,
            'score_source' => 'reviewer_confirmed',
            'created_at' => '2026-07-08 10:00:00',
            'worksite_uuid' => '55555555-5555-4555-8555-555555555555',
            'department_uuid' => '66666666-6666-4666-8666-666666666666',
            'job_role_uuid' => '77777777-7777-4777-8777-777777777777',
            'risk_factors' => 'forceful_exertion',
            'body_region_scores' => 'lower_back:back:4',
            'worker_feedback_count' => 2,
            'avg_discomfort_level' => 3.5,
        ]];
    }

    public function fetchWorkerFeedbackDataset(string $organizationUuid, array $filters, int $limit): array
    {
        return [];
    }

    public function countAssessmentDataset(string $organizationUuid, array $filters): int
    {
        return 1;
    }

    public function countWorkerFeedbackDataset(string $organizationUuid, array $filters): int
    {
        return 0;
    }
}

final class RecordingResearchExportStorageService implements IStorageService
{
    public string $storedBody = '';

    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO
    {
        $this->storedBody = file_get_contents((string) $request->file['tmp_name']) ?: '';

        return new StoredFileDTO(
            id: 1,
            uuid: 'stored-export-1',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'exports/research.csv',
            ownerType: 'export',
            ownerUuid: '11111111-1111-4111-8111-111111111111',
            fieldName: 'csv',
            originalName: 'research.csv',
            mimeType: 'text/csv',
            extension: 'csv',
            sizeBytes: strlen($this->storedBody),
        );
    }

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO
    {
        throw new \RuntimeException('Not needed.');
    }

    public function list(array $filters = []): array { return []; }
    public function count(array $filters = []): int { return 0; }
    public function summary(array $filters = []): array { return ['totalFiles' => 0, 'totalBytes' => 0, 'byCategory' => []]; }
    public function read(string $uuid): string { return $this->storedBody; }
    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not needed.'); }
    public function usageCount(string $uuid): int { return 0; }
}

final class AllowingResearchExportPermissionService implements \WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}

final class RecordingResearchExportAuditService implements IAuditService
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(string $action, string $entityType, string $entityId, ?array $beforeState = null, ?array $afterState = null, ?string $actorId = null, ?string $actorType = null, ?string $idempotencyKey = null, ?array $metadata = []): void
    {
        $this->records[] = compact('action', 'entityType', 'entityId', 'beforeState', 'afterState', 'actorId', 'actorType', 'idempotencyKey', 'metadata');
    }
}
