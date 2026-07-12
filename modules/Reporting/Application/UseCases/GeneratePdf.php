<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\UseCases;

use WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\Reporting\Application\Services\ReportArtifactService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Support\UuidSupport;
use Dompdf\Dompdf;
use Dompdf\Options;

final class GeneratePdf
{
    public function __construct(
        private readonly ReportingSnapshotService $snapshots,
        private readonly ReportArtifactService $artifacts,
        private readonly IStorageService $storage,
        private readonly ReportingSettings $settings,
        private readonly ISessionService $session,
        private readonly SettingsService $globalSettings,
        private readonly ?ContentPageReader $contentPages = null,
    ) {}

    public function generateDashboardPdf(): string
    {
        $data = $this->snapshots->dashboard();
        $data = $this->enrichData($data);
        $html = $this->renderTemplate('dashboard', $data);

        return $this->createAndStorePdf('dashboard', null, 'dashboard_report_' . date('Ymd_His') . '.pdf', 'dashboard', $data, $html);
    }

    public function generateFinancePdf(): string
    {
        $data = $this->snapshots->finance();
        $data = $this->enrichData($data);
        $html = $this->renderTemplate('finance', $data);

        return $this->createAndStorePdf('finance', null, 'finance_report_' . date('Ymd_His') . '.pdf', 'finance', $data, $html);
    }

    public function generateOperationsPdf(): string
    {
        $data = $this->snapshots->operations();
        $data = $this->enrichData($data);
        $html = $this->renderTemplate('operations', $data);

        return $this->createAndStorePdf('operations', null, 'operations_report_' . date('Ymd_His') . '.pdf', 'operations', $data, $html);
    }

    public function generatePilotSummaryPdf(?string $organizationUuid = null, array $filters = [], ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->pilotSummary($organizationUuid, $filters);
        $data = $this->enrichData($data);
        $html = $this->renderTemplate('pilot_summary', $data);

        return $this->createAndStorePdf(
            'pilot_summary',
            $organizationUuid,
            'pilot_summary_report_' . ($organizationUuid ?? 'global') . '_' . date('Ymd_His') . '.pdf',
            'pilot_summary',
            $data,
            $html,
            $previousArtifactUuid,
            $regenerationReason,
        );
    }

    public function generateAssessmentPdf(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->assessmentReport($uuid);
        $data = $this->enrichData($data);
        $html = $this->renderTemplate('assessment', $data);

        return $this->createAndStorePdf('assessment', $uuid, 'assessment_report_' . $uuid . '_' . date('Ymd_His') . '.pdf', 'assessment', $data, $html, $previousArtifactUuid, $regenerationReason);
    }

    public function generateCorrectiveActionPdf(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->correctiveActionReport($uuid);
        $data = $this->enrichData($data);
        $html = $this->renderTemplate('corrective_action', $data);

        return $this->createAndStorePdf('corrective_action', $uuid, 'corrective_action_report_' . $uuid . '_' . date('Ymd_His') . '.pdf', 'corrective_action', $data, $html, $previousArtifactUuid, $regenerationReason);
    }

    public function generateComparisonPdf(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->comparisonReport($uuid);
        $data = $this->enrichData($data);
        $data = $this->attachComparisonImages($data);
        $html = $this->renderTemplate('comparison', $data);

        return $this->createAndStorePdf('comparison', $uuid, 'comparison_report_' . $uuid . '_' . date('Ymd_His') . '.pdf', 'comparison', $data, $html, $previousArtifactUuid, $regenerationReason);
    }

    public function generateAuditTrailPdf(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->auditTrailReport($uuid);
        $data = $this->enrichData($data);
        $html = $this->renderTemplate('audit_trail', $data);

        return $this->createAndStorePdf('audit_trail', $uuid, 'audit_trail_report_' . $uuid . '_' . date('Ymd_His') . '.pdf', 'audit_trail', $data, $html, $previousArtifactUuid, $regenerationReason);
    }

    public function regenerate(
        string $reportType,
        ?string $sourceUuid,
        ?string $organizationUuid = null,
        ?string $previousArtifactUuid = null,
        ?string $regenerationReason = null,
    ): string {
        return match ($reportType) {
            'dashboard' => $this->generateDashboardPdf(),
            'finance' => $this->generateFinancePdf(),
            'operations' => $this->generateOperationsPdf(),
            'pilot_summary' => $this->generatePilotSummaryPdf($organizationUuid, [], $previousArtifactUuid, $regenerationReason),
            'assessment' => $this->generateAssessmentPdf($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            'corrective_action' => $this->generateCorrectiveActionPdf($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            'comparison' => $this->generateComparisonPdf($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            'audit_trail' => $this->generateAuditTrailPdf($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            default => throw new \InvalidArgumentException('Unsupported report type for PDF regeneration.'),
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function enrichData(array $data): array
    {
        $contentNotes = $this->contentNotes();
        $data['org'] = [
            'name' => (string) $this->globalSettings->get('billing.org_name', 'WorkEddy'),
            'address' => (string) $this->globalSettings->get('billing.org_address', ''),
            'phone' => (string) $this->globalSettings->get('billing.org_phone', ''),
            'email' => (string) $this->globalSettings->get('billing.org_email', ''),
            'tax_id' => (string) $this->globalSettings->get('billing.org_tax_id', ''),
        ];
        $data['notes'] = [
            'methodology' => $contentNotes['methodology'],
            'limitations' => $contentNotes['limitations'],
            'privacy' => $contentNotes['privacy'],
        ];
        if (isset($contentNotes['contentProvenance'])) {
            $data['contentProvenance'] = $contentNotes['contentProvenance'];
        }
        $data['template_version'] = $this->settings->templateVersion();
        $data['generated_at'] = gmdate('Y-m-d H:i:s');

        return $data;
    }

    /** @return array<string, mixed> */
    private function contentNotes(): array
    {
        $fallback = [
            'methodology' => $this->settings->methodologyNote(),
            'limitations' => $this->settings->limitationsNote(),
            'privacy' => $this->settings->privacyNote(),
        ];

        if ($this->contentPages === null) {
            return $fallback;
        }

        $page = $this->contentPages->findPublishedByKey(MethodologyPageDefinition::PAGE_KEY);
        if ($page === null) {
            return $fallback;
        }

        $sections = [];
        foreach ($page->sections as $section) {
            $sections[$section->sectionKey] = trim($section->plainText);
        }

        return [
            'methodology' => $sections['what_workeddy_measures'] ?? $fallback['methodology'],
            'limitations' => $sections['what_workeddy_does_not_claim'] ?? $fallback['limitations'],
            'privacy' => $sections['how_privacy_is_protected'] ?? $fallback['privacy'],
            'contentProvenance' => [
                'contentPageKey' => $page->key,
                'contentRevisionUuid' => $page->revisionUuid,
                'contentSnapshotHash' => $page->snapshotHash,
            ],
        ];
    }

    private function renderTemplate(string $name, array $data): string
    {
        ob_start();
        require __DIR__ . '/../../Presentation/Template/' . $name . '.php';
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attachComparisonImages(array $data): array
    {
        foreach (['baseline', 'follow_up'] as $key) {
            if (!is_array($data[$key] ?? null)) {
                continue;
            }

            $uuid = (string) ($data[$key]['screenshot_storage_file_uuid'] ?? '');
            $data[$key]['screenshot_data_uri'] = $this->fileDataUri($uuid);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function createAndStorePdf(
        string $reportType,
        ?string $sourceUuid,
        string $fileName,
        string $templateName,
        array $snapshot,
        string $html,
        ?string $previousArtifactUuid = null,
        ?string $regenerationReason = null,
    ): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output() ?? '';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($tempPath, $pdfContent);
        $artifactUuid = UuidSupport::generate();
        $actorId = $this->actorId();

        $request = new StoreUploadedFileRequest(
            file: [
                'tmp_name' => $tempPath,
                'name' => $fileName,
                'type' => 'application/pdf',
                'size' => filesize($tempPath),
                'error' => UPLOAD_ERR_OK,
            ],
            ownerType: 'reporting',
            ownerUuid: $artifactUuid,
            fieldName: 'pdf',
            visibility: 'private',
            actorId: $actorId
        );

        $storedFile = $this->storage->storeUploadedFile($request);
        unlink($tempPath);

        if (!$storedFile) {
            throw new \RuntimeException('Failed to store generated PDF.');
        }

        $this->artifacts->register(
            artifactUuid: $artifactUuid,
            reportType: $reportType,
            sourceUuid: $sourceUuid,
            previousArtifactUuid: $previousArtifactUuid,
            regenerationReason: $regenerationReason,
            format: 'pdf',
            storageFileUuid: $storedFile->uuid,
            templateName: $templateName,
            templateVersion: $this->settings->templateVersion(),
            snapshot: $snapshot,
            generatedByUserId: $actorId,
        );

        return $storedFile->uuid;
    }

    private function requireSourceUuid(string $reportType, ?string $sourceUuid): string
    {
        if ($sourceUuid === null || trim($sourceUuid) === '') {
            throw new \InvalidArgumentException('Missing source UUID for report type ' . $reportType . '.');
        }

        return $sourceUuid;
    }

    private function actorId(): ?int
    {
        $context = $this->session->getUserContext();
        if ($context === null || !is_numeric((string) $context->userId)) {
            return null;
        }

        return (int) $context->userId;
    }

    private function fileDataUri(string $uuid): ?string
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        try {
            $file = $this->storage->findByUuid($uuid, true);
            if (!str_starts_with((string) $file->mimeType, 'image/')) {
                return null;
            }

            $content = $this->storage->read($uuid);
            if ($content === '') {
                return null;
            }

            return 'data:' . $file->mimeType . ';base64,' . base64_encode($content);
        } catch (\Throwable) {
            return null;
        }
    }
}
