<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Reporting;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentImage;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentReference;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentSection;
use WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\Reporting\Application\Services\ReportArtifactService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Reporting\Domain\ReportArtifact;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Application\DTOs\StoredFileDTO;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;

final class ReportingContentNotesTest extends TestCase
{
    public function test_generate_pdf_prefers_published_content_notes_and_records_provenance(): void
    {
        $conn = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $conn->method('createSchemaManager')->willReturn($schemaManager);
        $conn->method('fetchOne')->willReturn(0);
        $conn->method('fetchAllAssociative')->willReturn([]);

        $storage = new RecordingStorageService();
        $artifacts = new RecordingReportArtifactRepository();
        $settingsService = new SettingsService([
            'billing.org_name' => 'WorkEddy',
            'reporting.template_version' => 'v-content',
            'reporting.methodology_note' => 'Fallback methodology note.',
            'reporting.limitations_note' => 'Fallback limitations note.',
            'reporting.privacy_note' => 'Fallback privacy note.',
        ]);

        $useCase = new GeneratePdf(
            new ReportingSnapshotService($conn),
            new ReportArtifactService($artifacts),
            $storage,
            new ReportingSettings($settingsService),
            $this->createMock(ISessionService::class),
            $settingsService,
            new FakePublishedMethodologyReader(),
        );

        $uuid = $useCase->generateFinancePdf();

        self::assertSame('stored-report-file', $uuid);
        self::assertNotNull($artifacts->created);
        self::assertSame('methodology-and-limitations', $artifacts->created->snapshotPayload['contentProvenance']['contentPageKey'] ?? null);
        self::assertSame('revision-methodology-1', $artifacts->created->snapshotPayload['contentProvenance']['contentRevisionUuid'] ?? null);
        self::assertSame('What WorkEddy measures summary.', $artifacts->created->snapshotPayload['notes']['methodology'] ?? null);
        self::assertSame('What WorkEddy does not claim summary.', $artifacts->created->snapshotPayload['notes']['limitations'] ?? null);
        self::assertSame('How privacy is protected summary.', $artifacts->created->snapshotPayload['notes']['privacy'] ?? null);
    }

    public function test_generate_pdf_falls_back_to_reporting_settings_when_content_is_absent(): void
    {
        $conn = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $conn->method('createSchemaManager')->willReturn($schemaManager);
        $conn->method('fetchOne')->willReturn(0);
        $conn->method('fetchAllAssociative')->willReturn([]);

        $storage = new RecordingStorageService();
        $artifacts = new RecordingReportArtifactRepository();
        $settingsService = new SettingsService([
            'billing.org_name' => 'WorkEddy',
            'reporting.template_version' => 'v-content',
            'reporting.methodology_note' => 'Fallback methodology note.',
            'reporting.limitations_note' => 'Fallback limitations note.',
            'reporting.privacy_note' => 'Fallback privacy note.',
        ]);

        $useCase = new GeneratePdf(
            new ReportingSnapshotService($conn),
            new ReportArtifactService($artifacts),
            $storage,
            new ReportingSettings($settingsService),
            $this->createMock(ISessionService::class),
            $settingsService,
            new NullPublishedContentReader(),
        );

        $useCase->generateFinancePdf();

        self::assertNotNull($artifacts->created);
        self::assertSame('Fallback methodology note.', $artifacts->created->snapshotPayload['notes']['methodology'] ?? null);
        self::assertSame('Fallback limitations note.', $artifacts->created->snapshotPayload['notes']['limitations'] ?? null);
        self::assertSame('Fallback privacy note.', $artifacts->created->snapshotPayload['notes']['privacy'] ?? null);
        self::assertArrayNotHasKey('contentProvenance', $artifacts->created->snapshotPayload);
    }
}

final class RecordingStorageService implements IStorageService
{
    public function storeUploadedFile(StoreUploadedFileRequest $request): ?StoredFileDTO
    {
        return new StoredFileDTO(
            id: 1,
            uuid: 'stored-report-file',
            disk: 'local',
            visibility: 'private',
            status: 'active',
            path: 'reporting/output.pdf',
            ownerType: $request->ownerType,
            ownerUuid: $request->ownerUuid,
            fieldName: $request->fieldName,
            originalName: $request->file['name'],
            mimeType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 1024,
            createdAt: '2026-07-11 00:00:00',
            updatedAt: '2026-07-11 00:00:00',
        );
    }

    public function findByUuid(string $uuid, bool $includePendingDeletion = false): StoredFileDTO { throw new \RuntimeException('Not implemented.'); }
    public function list(array $filters = []): array { return []; }
    public function count(array $filters = []): int { return 0; }
    public function summary(array $filters = []): array { return ['totalFiles' => 0, 'totalBytes' => 0, 'byCategory' => []]; }
    public function read(string $uuid): string { return ''; }
    public function requestDeletion(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not implemented.'); }
    public function restore(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not implemented.'); }
    public function delete(string $uuid, ?int $actorId = null): StoredFileDTO { throw new \RuntimeException('Not implemented.'); }
    public function usageCount(string $uuid): int { return 0; }
}

final class RecordingReportArtifactRepository implements IReportArtifactRepository
{
    public ?ReportArtifact $created = null;

    public function create(ReportArtifact $artifact): int
    {
        $this->created = $artifact;

        return 1;
    }

    public function findByUuid(string $uuid): ?ReportArtifact { return null; }
    public function findByStorageFileUuid(string $storageFileUuid): ?ReportArtifact { return null; }
    public function listByReportSource(string $reportType, ?string $sourceUuid, int $limit = 20): array { return []; }
    public function listVersionChain(string $artifactUuid, int $limit = 20): array { return []; }
}

final class FakePublishedMethodologyReader implements ContentPageReader
{
    public function findPublishedByKey(string $pageKey): ?PublishedContentPage
    {
        if ($pageKey !== MethodologyPageDefinition::PAGE_KEY) {
            return null;
        }

        return new PublishedContentPage(
            key: MethodologyPageDefinition::PAGE_KEY,
            title: 'Methodology and Limitations',
            audience: 'internal',
            templateKey: 'internal_methodology',
            sections: [
                new PublishedContentSection('what_workeddy_measures', 'What WorkEddy measures', [], 1, 'What WorkEddy measures summary.'),
                new PublishedContentSection('scoring_systems', 'Scoring systems', [], 2, 'Scoring systems summary.'),
                new PublishedContentSection('how_ai_scoring_works', 'How AI scoring works', [], 3, 'AI scoring summary.'),
                new PublishedContentSection('why_reviewer_validation', 'Why reviewer validation is included', [], 4, 'Reviewer validation summary.'),
                new PublishedContentSection('what_workeddy_does_not_claim', 'What WorkEddy does not claim', [], 5, 'What WorkEddy does not claim summary.'),
                new PublishedContentSection('how_privacy_is_protected', 'How privacy is protected', [], 6, 'How privacy is protected summary.'),
                new PublishedContentSection('how_data_supports_prevention', 'How data supports prevention', [], 7, 'How data supports prevention summary.'),
                new PublishedContentSection('how_pilot_evidence_is_collected', 'How pilot evidence is collected', [], 8, 'Pilot evidence summary.'),
            ],
            references: [
                new PublishedContentReference('scoring_systems', 'REBA reference', 'Hignett', '2000', null, 'Citation', 1),
            ],
            images: [],
            revisionUuid: 'revision-methodology-1',
            publishedAt: new \DateTimeImmutable('2026-07-11 00:00:00'),
            snapshotHash: 'snapshot-methodology-1',
        );
    }
}

final class NullPublishedContentReader implements ContentPageReader
{
    public function findPublishedByKey(string $pageKey): ?PublishedContentPage
    {
        return null;
    }
}
