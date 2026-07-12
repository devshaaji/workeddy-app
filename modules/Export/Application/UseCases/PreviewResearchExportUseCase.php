<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Application\UseCases;

use WorkEddy\Modules\Export\Application\DTOs\ResearchExportPreview;
use WorkEddy\Modules\Export\Application\Support\ResearchExportColumnCatalog;
use WorkEddy\Modules\Export\Authorization\ExportPermissions;
use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Settings\ExportSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;

final class PreviewResearchExportUseCase
{
    public function __construct(
        private readonly IResearchExportRepository $exports,
        private readonly ResearchExportColumnCatalog $columns,
        private readonly IPermissionService $permissions,
        private readonly ExportSettings $settings,
    ) {}

    /**
     * @param array<string, mixed> $filters
     */
    public function execute(string $dataset, string $format, UserContext $actor, array $filters = []): ResearchExportPreview
    {
        $this->permissions->requirePrivilege($actor, ExportPermissions::PREVIEW);
        $dataset = $this->normalizeDataset($dataset);
        $format = $this->normalizeFormat($format);
        $organizationUuid = (string) ($actor->organizationUuid ?? '');

        $count = match ($dataset) {
            'worker_feedback' => $this->exports->countWorkerFeedbackDataset($organizationUuid, $filters),
            default => $this->exports->countAssessmentDataset($organizationUuid, $filters),
        };

        return new ResearchExportPreview(
            dataset: $dataset,
            format: $format,
            includedColumns: $this->columns->includedColumns($dataset),
            excludedFields: $this->columns->excludedFields($dataset),
            transformations: $this->columns->transformations($dataset),
            estimatedRows: min($count, $this->settings->maxExportRows()),
        );
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
