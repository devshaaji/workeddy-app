<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Application\UseCases;

use WorkEddy\Modules\Export\Application\Services\ResearchExportDeidentificationService;
use WorkEddy\Modules\Export\Application\Services\ResearchExportFileWriter;
use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Domain\ResearchExport;
use WorkEddy\Modules\Export\Authorization\ExportPermissions;
use WorkEddy\Modules\Export\Settings\ExportSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class GenerateResearchExportUseCase
{
    public function __construct(
        private readonly IResearchExportRepository $exports,
        private readonly ResearchExportDeidentificationService $deidentify,
        private readonly ResearchExportFileWriter $writer,
        private readonly IStorageService $storage,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ExportSettings $settings,
    ) {}

    /**
     * @param array<string, mixed> $filters
     */
    public function execute(string $dataset, string $format, UserContext $actor, array $filters = []): ResearchExport
    {
        $this->permissions->requirePrivilege($actor, ExportPermissions::GENERATE);
        $organizationId = (int) ($actor->organizationId ?? 0);
        $organizationUuid = (string) ($actor->organizationUuid ?? '');
        if ($organizationId <= 0 || $organizationUuid === '') {
            throw new ValidationException(['organization' => 'Research exports require an organization-scoped user context.']);
        }

        $dataset = $this->normalizeDataset($dataset);
        $format = $this->normalizeFormat($format);
        $exportUuid = UuidSupport::generate();

        $pending = new ResearchExport(
            id: null,
            uuid: $exportUuid,
            organizationId: $organizationId,
            organizationUuid: $organizationUuid,
            dataset: $dataset,
            format: $format,
            status: 'pending',
            filters: $filters,
            columnSchema: [],
            deidentificationProfile: $this->settings->deidentificationProfile(),
            storageFileUuid: null,
            rowCount: null,
            generatedByUserId: is_numeric((string) $actor->userId) ? (int) $actor->userId : null,
            generatedAt: null,
            expiresAt: null,
        );
        $this->exports->create($pending);

        $sourceRows = match ($dataset) {
            'worker_feedback' => $this->exports->fetchWorkerFeedbackDataset($organizationUuid, $filters, $this->settings->maxExportRows()),
            default => $this->exports->fetchAssessmentDataset($organizationUuid, $filters, $this->settings->maxExportRows()),
        };
        $transformed = $this->deidentify->transform($dataset, $sourceRows);
        $path = $this->writer->write($format, $dataset, $transformed['rows']);
        $filename = sprintf('research_export_%s_%s.%s', $dataset, date('Ymd_His'), $format);
        $mimeType = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';
        $allowedMimeTypes = $format === 'xlsx'
            ? ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']
            : ['text/csv', 'text/plain'];

        $stored = $this->storage->storeUploadedFile(new StoreUploadedFileRequest(
            file: [
                'tmp_name' => $path,
                'name' => $filename,
                'type' => $mimeType,
                'size' => filesize($path),
                'error' => UPLOAD_ERR_OK,
            ],
            ownerType: 'export',
            ownerUuid: $exportUuid,
            fieldName: $format,
            visibility: 'private',
            actorId: is_numeric((string) $actor->userId) ? (int) $actor->userId : null,
            allowedExtensions: [$format],
            allowedMimeTypes: $allowedMimeTypes,
        ));
        @unlink($path);

        if ($stored === null) {
            throw new \RuntimeException('Failed to store generated research export.');
        }

        foreach ($transformed['codeMaps'] as $entityType => $maps) {
            $this->exports->replaceCodeMaps($exportUuid, $entityType, $maps);
        }

        $ready = new ResearchExport(
            id: null,
            uuid: $exportUuid,
            organizationId: $organizationId,
            organizationUuid: $organizationUuid,
            dataset: $dataset,
            format: $format,
            status: 'ready',
            filters: $filters,
            columnSchema: $transformed['columnSchema'],
            deidentificationProfile: $this->settings->deidentificationProfile(),
            storageFileUuid: $stored->uuid,
            rowCount: count($transformed['rows']),
            generatedByUserId: is_numeric((string) $actor->userId) ? (int) $actor->userId : null,
            generatedAt: gmdate('Y-m-d H:i:s'),
            expiresAt: null,
        );
        $this->exports->update($ready);

        $this->audit->record(
            'export.research.generated',
            'research_export',
            $exportUuid,
            afterState: $ready->toView(),
            actorId: (string) $actor->userId,
            actorType: 'user',
            metadata: [
                'dataset' => $dataset,
                'format' => $format,
                'filters' => $filters,
            ],
        );

        $saved = $this->exports->findByUuid($exportUuid);
        if ($saved === null) {
            throw new \RuntimeException('Research export was created but could not be reloaded.');
        }

        return $saved;
    }

    private function normalizeDataset(string $dataset): string
    {
        $dataset = strtolower(trim($dataset));

        return in_array($dataset, ['assessments', 'worker_feedback'], true) ? $dataset : 'assessments';
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        if (!in_array($format, $this->settings->allowedFormats(), true)) {
            return $this->settings->defaultFormat();
        }

        return $format;
    }
}
