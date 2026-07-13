<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Presentation;

use WorkEddy\Modules\Reporting\Application\IssueSignedReportAccessUseCase;
use WorkEddy\Modules\Reporting\Application\RegenerateReportArtifactUseCase;
use WorkEddy\Modules\Reporting\Application\ReadSignedReportAccessUseCase;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Reporting\Application\UseCases\GenerateCsv;
use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;

final class ReportingApiController
{
    public function __construct(
        private readonly ReportingSnapshotService $snapshots,
        private readonly GeneratePdf $generatePdf,
        private readonly GenerateCsv $generateCsv,
        private readonly RegenerateReportArtifactUseCase $regenerateArtifactUseCase,
        private readonly IReportArtifactRepository $artifacts,
        private readonly IssueSignedReportAccessUseCase $issueSignedAccess,
        private readonly ReadSignedReportAccessUseCase $readSignedAccess,
        private readonly IStorageService $storage,
        private readonly ISessionService $session,
        private readonly IAuditService $audit,
    ) {}

    public function dashboard(Request $request): Response
    {
        return Response::success($this->snapshots->dashboard());
    }

    public function summary(Request $request): Response
    {
        return $this->dashboard($request);
    }

    public function finance(Request $request): Response
    {
        return Response::success($this->snapshots->finance());
    }

    public function operations(Request $request): Response
    {
        return Response::success($this->snapshots->operations());
    }

    public function pilotSummary(Request $request): Response
    {
        $context = $this->requireContext();

        return Response::success($this->snapshots->pilotSummary($context->organizationUuid, $this->pilotFilters($request)));
    }

    public function impactTracker(Request $request): Response
    {
        $context = $this->requireContext();

        return Response::success($this->snapshots->impactTracker($context->organizationUuid, $this->pilotFilters($request)));
    }

    public function downloadImpactTrackerPdf(Request $request): Response
    {
        $context = $this->requireContext();
        $fileUuid = $this->generatePdf->generateImpactTrackerPdf($context->organizationUuid, $this->pilotFilters($request));

        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadDashboardPdf(Request $request): Response
    {
        $fileUuid = $this->generatePdf->generateDashboardPdf();
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadDashboardCsv(Request $request): Response
    {
        $fileUuid = $this->generateCsv->generateDashboardCsv();
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadFinancePdf(Request $request): Response
    {
        $fileUuid = $this->generatePdf->generateFinancePdf();
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadFinanceCsv(Request $request): Response
    {
        $fileUuid = $this->generateCsv->generateFinanceCsv();
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadOperationsPdf(Request $request): Response
    {
        $fileUuid = $this->generatePdf->generateOperationsPdf();
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadOperationsCsv(Request $request): Response
    {
        $fileUuid = $this->generateCsv->generateOperationsCsv();
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadPilotSummaryPdf(Request $request): Response
    {
        $context = $this->requireContext();
        $fileUuid = $this->generatePdf->generatePilotSummaryPdf($context->organizationUuid, $this->pilotFilters($request));

        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadPilotSummaryCsv(Request $request): Response
    {
        $context = $this->requireContext();
        $fileUuid = $this->generateCsv->generatePilotSummaryCsv($context->organizationUuid, $this->pilotFilters($request));

        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function assessment(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        return Response::success($this->snapshots->assessmentReport($uuid));
    }

    public function downloadAssessmentPdf(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generatePdf->generateAssessmentPdf($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadAssessmentCsv(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generateCsv->generateAssessmentCsv($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function correctiveAction(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        return Response::success($this->snapshots->correctiveActionReport($uuid));
    }

    public function downloadCorrectiveActionPdf(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generatePdf->generateCorrectiveActionPdf($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadCorrectiveActionCsv(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generateCsv->generateCorrectiveActionCsv($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function comparison(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        return Response::success($this->snapshots->comparisonReport($uuid));
    }

    public function downloadComparisonPdf(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generatePdf->generateComparisonPdf($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadComparisonCsv(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generateCsv->generateComparisonCsv($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function auditTrail(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        return Response::success($this->snapshots->auditTrailReport($uuid));
    }

    public function downloadAuditTrailPdf(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generatePdf->generateAuditTrailPdf($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function downloadAuditTrailCsv(Request $request): Response
    {
        $uuid = (string) $request->routeParam('uuid');
        $fileUuid = $this->generateCsv->generateAuditTrailCsv($uuid);
        return $this->redirectToSignedAccess($fileUuid, $request);
    }

    public function listArtifacts(Request $request): Response
    {
        $reportType = (string) ($request->query('reportType') ?? $request->query('report_type') ?? '');
        $sourceUuid = $request->query('sourceUuid') ?? $request->query('source_uuid');
        $limit = (int) ($request->query('limit') ?? 20);

        $items = array_map(
            static fn($artifact): array => $artifact->toView(),
            $this->artifacts->listByReportSource($reportType, is_string($sourceUuid) && $sourceUuid !== '' ? $sourceUuid : null, $limit),
        );

        return Response::success([
            'reportType' => $reportType,
            'sourceUuid' => is_string($sourceUuid) && $sourceUuid !== '' ? $sourceUuid : null,
            'items' => $items,
        ]);
    }

    public function versionChain(Request $request): Response
    {
        $artifactUuid = (string) ($request->routeParam('artifactUuid') ?? '');
        $limit = (int) ($request->query('limit') ?? 20);

        return Response::success([
            'artifactUuid' => $artifactUuid,
            'items' => array_map(
                static fn($artifact): array => $artifact->toView(),
                $this->artifacts->listVersionChain($artifactUuid, $limit),
            ),
        ]);
    }

    public function regenerateArtifact(Request $request): Response
    {
        $body = array_replace($request->query, $request->body, $request->json);
        $artifactUuid = (string) ($request->routeParam('artifactUuid') ?? $body['artifactUuid'] ?? $body['artifact_uuid'] ?? '');
        $context = $this->requireContext();
        $artifact = $this->regenerateArtifactUseCase->execute(
            $artifactUuid,
            $context,
            is_string($body['reason'] ?? null) ? (string) $body['reason'] : null,
            is_string($body['format'] ?? null) ? (string) $body['format'] : null,
        );
        $signed = $this->issueSignedAccess->execute($artifact->uuid, $context, 'regenerate-download');

        return Response::success([
            'artifact' => $artifact->toView(),
            'signedAccess' => $signed,
        ], status: 201);
    }

    public function issueSignedAccess(Request $request): Response
    {
        $body = array_replace($request->query, $request->body, $request->json);
        $artifactUuid = (string) ($body['artifactUuid'] ?? $body['artifact_uuid'] ?? $request->routeParam('artifactUuid') ?? '');
        $context = $this->requireContext();

        return Response::success($this->issueSignedAccess->execute(
            $artifactUuid,
            $context,
            (string) ($body['purpose'] ?? 'download'),
        ));
    }

    public function readSignedAccess(Request $request): Response
    {
        $stream = $this->readSignedAccess->execute((string) ($request->routeParam('token') ?? ''));

        return Response::stream(static function () use ($stream): void {
            echo $stream['body'];
        }, headers: [
            'Content-Type' => $stream['mimeType'],
            'Content-Disposition' => $stream['disposition'] . '; filename="' . str_replace(['"', "\r", "\n"], '', $stream['filename']) . '"',
        ]);
    }

    private function redirectToSignedAccess(string $storageFileUuid, Request $request): Response
    {
        $artifact = $this->artifacts->findByStorageFileUuid($storageFileUuid);
        if ($artifact === null) {
            throw new \RuntimeException('Generated report artifact was not registered.');
        }
        $context = $this->requireContext();
        $signed = $this->issueSignedAccess->execute($artifact->uuid, $context);
        $accept = strtolower((string) ($request->header('accept') ?? ''));

        if (str_contains($accept, 'application/json')) {
            return Response::success($signed);
        }

        return Response::redirect((string) $signed['signedUrl']);
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $context = $this->session->getUserContext();
        if ($context === null) {
            throw new \WorkEddy\Shared\Exceptions\AuthenticationException('Authentication required.');
        }

        return $context;
    }

    /** @return array<string, string> */
    private function pilotFilters(Request $request): array
    {
        return [
            'industry' => trim((string) $request->query('industry', '')),
            'worksiteUuid' => trim((string) $request->query('worksiteUuid', '')),
            'departmentUuid' => trim((string) $request->query('departmentUuid', '')),
            'jobRoleUuid' => trim((string) $request->query('jobRoleUuid', '')),
            'bodyRegion' => trim((string) $request->query('bodyRegion', '')),
            'fromDate' => trim((string) $request->query('fromDate', '')),
            'toDate' => trim((string) $request->query('toDate', '')),
            'riskLevel' => trim((string) $request->query('riskLevel', '')),
        ];
    }

    private function streamStoredFile(string $fileUuid, string $reportType, ?string $sourceUuid, string $format): Response
    {
        $file = $this->storage->findByUuid($fileUuid);
        $content = $this->storage->read($fileUuid);
        $context = $this->session->getUserContext();
        $this->audit->record(
            'reporting.report.downloaded',
            'report_artifact',
            $fileUuid,
            actorId: $context !== null ? (string) $context->userId : null,
            actorType: 'user',
            metadata: [
                'reportType' => $reportType,
                'sourceUuid' => $sourceUuid,
                'format' => $format,
                'fileUuid' => $fileUuid,
                'originalName' => $file->originalName,
            ],
        );

        return Response::stream(
            static function () use ($content) {
                echo $content;
            },
            200,
            [
                'Content-Type' => $file->mimeType,
                'Content-Disposition' => 'attachment; filename="' . $file->originalName . '"',
                'Content-Length' => (string) strlen($content),
            ]
        );
    }
}
