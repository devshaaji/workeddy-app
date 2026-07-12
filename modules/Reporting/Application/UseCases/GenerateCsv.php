<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\UseCases;

use WorkEddy\Modules\Reporting\Application\Services\ReportArtifactService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Support\CsvSecurity;
use WorkEddy\Shared\Support\UuidSupport;

final class GenerateCsv
{
    public function __construct(
        private readonly ReportingSnapshotService $snapshots,
        private readonly ReportArtifactService $artifacts,
        private readonly IStorageService $storage,
        private readonly ReportingSettings $settings,
        private readonly ISessionService $session,
    ) {}

    public function generateDashboardCsv(): string
    {
        $data = $this->snapshots->dashboard();
        $rows = [
            ['Management Dashboard Report Summary'],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            ['Category', 'Metric', 'Value'],
            ['Customer Summary', 'Total Customers', $data['customer_summary']['total_customers'] ?? 0],
            ['Customer Summary', 'Active Customers', $data['customer_summary']['active_customers'] ?? 0],
            ['Finance Summary', 'Income Total', $data['finance_summary']['income_total'] ?? 0],
            ['Finance Summary', 'Expense Total', $data['finance_summary']['expense_total'] ?? 0],
            ['Finance Summary', 'Payroll Gross Total', $data['finance_summary']['payroll_gross_total'] ?? 0],
            ['Ticket Summary', 'Open Tickets', $data['ticket_summary']['open_tickets'] ?? 0],
            ['Installation Summary', 'Completed Installations', $data['installation_summary']['completed_installations'] ?? 0],
            ['Inventory Summary', 'Low Stock Items', $data['inventory_summary']['low_stock_items'] ?? 0],
            ['Staff Summary', 'Active Employees', $data['staff_summary']['active_employees'] ?? 0],
        ];

        return $this->writeAndStoreCsv('dashboard', null, 'dashboard_report_' . date('Ymd_His') . '.csv', 'dashboard', $data, $rows);
    }

    public function generateFinanceCsv(): string
    {
        $data = $this->snapshots->finance();
        $rows = [
            ['Finance Report Summary'],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            ['Summary Metric', 'Value'],
            ['Income Total', $data['finance_summary']['income_total'] ?? 0],
            ['Expense Total', $data['finance_summary']['expense_total'] ?? 0],
            ['Payroll Gross Total', $data['finance_summary']['payroll_gross_total'] ?? 0],
            [],
            ['Income by Category'],
            ['Category', 'Total'],
        ];

        foreach ($data['income_by_category'] ?? [] as $item) {
            $rows[] = [$item['category'], $item['total']];
        }

        $rows[] = [];
        $rows[] = ['Expense by Category'];
        $rows[] = ['Category', 'Total'];
        foreach ($data['expense_by_category'] ?? [] as $item) {
            $rows[] = [$item['category'], $item['total']];
        }

        $rows[] = [];
        $rows[] = ['Payroll Periods'];
        $rows[] = ['Period Key', 'Gross Amount', 'Net Amount', 'Employee Count'];
        foreach ($data['payroll_periods'] ?? [] as $item) {
            $rows[] = [$item['period_key'], $item['gross_amount'], $item['net_amount'], $item['employee_count']];
        }

        return $this->writeAndStoreCsv('finance', null, 'finance_report_' . date('Ymd_His') . '.csv', 'finance', $data, $rows);
    }

    public function generateOperationsCsv(): string
    {
        $data = $this->snapshots->operations();
        $rows = [
            ['Operations Report Summary'],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            ['Area', 'Metric', 'Value'],
            ['Tickets', 'Open Tickets', $data['ticket_summary']['open_tickets'] ?? 0],
            ['Installations', 'Completed Installations', $data['installation_summary']['completed_installations'] ?? 0],
            ['Inventory', 'Low Stock Items', $data['inventory_summary']['low_stock_items'] ?? 0],
            ['Staffing', 'Active Employees', $data['staff_summary']['active_employees'] ?? 0],
            ['Customers', 'Total Customers', $data['customer_summary']['total_customers'] ?? 0],
            ['Customers', 'Active Customers', $data['customer_summary']['active_customers'] ?? 0],
        ];

        return $this->writeAndStoreCsv('operations', null, 'operations_report_' . date('Ymd_His') . '.csv', 'operations', $data, $rows);
    }

    public function generatePilotSummaryCsv(?string $organizationUuid = null, array $filters = [], ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->pilotSummary($organizationUuid, $filters);
        $rows = [
            ['Pilot Summary Report'],
            ['Organization UUID', $organizationUuid ?? 'global'],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            ['Applied Filters'],
            ['Industry', $data['filters']['industry'] ?? ''],
            ['Worksite UUID', $data['filters']['worksiteUuid'] ?? ''],
            ['Department UUID', $data['filters']['departmentUuid'] ?? ''],
            ['Job Role UUID', $data['filters']['jobRoleUuid'] ?? ''],
            ['Body Region', $data['filters']['bodyRegion'] ?? ''],
            ['From Date', $data['filters']['fromDate'] ?? ''],
            ['To Date', $data['filters']['toDate'] ?? ''],
            ['Risk Level', $data['filters']['riskLevel'] ?? ''],
            [],
            ['Implementation Metrics'],
            ['Metric', 'Value'],
            ['Worksites Enrolled', $data['summary']['worksites_enrolled'] ?? 0],
            ['Workers Participating', $data['summary']['workers_participating'] ?? 0],
            ['Task Videos Uploaded', $data['summary']['task_videos_uploaded'] ?? 0],
            ['Assessments Completed', $data['summary']['assessments'] ?? 0],
            ['Reviewed Assessments', $data['summary']['reviewed_assessments'] ?? 0],
            ['Baseline Assessments', $data['summary']['baseline_assessments'] ?? 0],
            ['Corrective Actions Total', $data['summary']['corrective_actions_total'] ?? 0],
            ['Corrective Actions Assigned', $data['summary']['corrective_actions_assigned'] ?? 0],
            ['Corrective Actions Completed', $data['summary']['corrective_actions_completed'] ?? 0],
            ['Corrective Actions Overdue', $data['summary']['corrective_actions_overdue'] ?? 0],
            [],
            ['Outcome Metrics'],
            ['Metric', 'Value'],
            ['High Risk Tasks Identified', $data['summary']['high_risk_tasks_identified'] ?? 0],
            ['Comparison Reports', $data['summary']['comparison_reports'] ?? 0],
            ['Average Closure Days', $data['summary']['average_closure_days'] ?? 0],
            ['Average Risk Reduction %', $data['summary']['average_risk_reduction_pct'] ?? 0],
            ['Worker Feedback Total', $data['summary']['worker_feedback_total'] ?? 0],
            ['Supervisor Feedback Total', $data['summary']['supervisor_feedback_total'] ?? 0],
            ['Anonymous Rate', $data['summary']['worker_feedback_anonymous_rate'] ?? 0],
            ['Average Discomfort', $data['summary']['average_discomfort'] ?? 0],
            ['Average Pain 30 Day', $data['summary']['average_pain_30'] ?? 0],
            ['Reviewer Agreement Rate %', $data['summary']['reviewer_agreement_rate'] ?? 0],
            ['Validation Review Pair Count', $data['summary']['reviewer_pair_count'] ?? 0],
            [],
            ['Top Body Regions'],
            ['Body Region', 'Responses', 'Average Discomfort'],
        ];

        foreach ($data['top_body_regions'] ?? [] as $item) {
            $rows[] = [$item['bodyRegion'] ?? 'Unknown', $item['responses'] ?? 0, $item['averageDiscomfort'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Top Tasks'];
        $rows[] = ['Task', 'Responses', 'Average Discomfort'];
        foreach ($data['top_tasks'] ?? [] as $item) {
            $rows[] = [$item['taskName'] ?? 'Unlinked', $item['responses'] ?? 0, $item['averageDiscomfort'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Supervisor Body Region Trends'];
        $rows[] = ['Body Region', 'Observations', 'Average Severity'];
        foreach ($data['supervisor_top_body_regions'] ?? [] as $item) {
            $rows[] = [$item['bodyRegion'] ?? 'Unspecified', $item['responses'] ?? 0, $item['averageSeverity'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Supervisor Feedback Timeline'];
        $rows[] = ['Date', 'Observations', 'Average Severity'];
        foreach ($data['supervisor_timeline'] ?? [] as $item) {
            $rows[] = [$item['date'] ?? '', $item['responses'] ?? 0, $item['averageSeverity'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Validation Agreement'];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Overall Agreement Rate', $data['validation_agreement']['overallAgreementRate'] ?? 0];
        $rows[] = ['Risk Level Agreement Rate', $data['validation_agreement']['riskLevelAgreementRate'] ?? 0];
        $rows[] = ['Score Agreement Rate', $data['validation_agreement']['scoreAgreementRate'] ?? 0];
        $rows[] = ['Body Region Agreement Rate', $data['validation_agreement']['bodyRegionAgreementRate'] ?? 0];
        $rows[] = ['Review Pair Count', $data['validation_agreement']['pairCount'] ?? 0];

        return $this->writeAndStoreCsv(
            'pilot_summary',
            $organizationUuid,
            'pilot_summary_report_' . ($organizationUuid ?? 'global') . '_' . date('Ymd_His') . '.csv',
            'pilot_summary',
            $data,
            $rows,
            $previousArtifactUuid,
            $regenerationReason,
        );
    }

    public function generateAssessmentCsv(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->assessmentReport($uuid);
        $rows = [
            ['Ergonomic Assessment Report'],
            ['Report UUID', $data['uuid']],
            ['Date Conducted', $data['date']],
            ['Worksite', $data['worksite']],
            ['Task Evaluated', $data['task']],
            ['Assessor', $data['assessor']],
            ['Professional Reviewer', $data['reviewer']],
            ['Risk Score', $data['risk_score']],
            ['Risk Severity Level', $data['risk_level']],
            ['Report Score Status', $data['report_score_status'] ?? 'reviewer_confirmed'],
            ['AI Advisory Available', !empty($data['ai_advisory']['available']) ? 'Yes' : 'No'],
            ['AI Advisory Message', $data['ai_advisory']['message'] ?? ''],
            [],
            ['Body Region Postural Scores'],
            ['Body Part', 'Score (out of 5)'],
        ];

        foreach ($data['body_region_scores'] as $part => $score) {
            $rows[] = [ucfirst($part), $score];
        }

        $rows[] = [];
        $rows[] = ['Primary Risk Factors'];
        foreach ($data['risk_factors'] as $factor) {
            $rows[] = [$factor];
        }

        $rows[] = [];
        $rows[] = ['Ergonomic Recommendations'];
        foreach ($data['recommendations'] as $rec) {
            $rows[] = [$rec];
        }

        return $this->writeAndStoreCsv('assessment', $uuid, 'assessment_report_' . $uuid . '_' . date('Ymd_His') . '.csv', 'assessment', $data, $rows, $previousArtifactUuid, $regenerationReason);
    }

    public function generateCorrectiveActionCsv(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->correctiveActionReport($uuid);
        $rows = [
            ['Corrective Action Report Summary'],
            ['Report UUID', $data['uuid']],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            ['Action ID', 'Title', 'Assignee', 'Due Date', 'Status', 'Evidence Ref'],
        ];

        foreach ($data['actions'] as $action) {
            $rows[] = [
                '#' . $action['id'],
                $action['title'],
                $action['assignee'],
                $action['due_date'],
                $action['status'],
                $action['evidence'] ?? 'None',
            ];
        }

        return $this->writeAndStoreCsv('corrective_action', $uuid, 'corrective_action_report_' . $uuid . '_' . date('Ymd_His') . '.csv', 'corrective_action', $data, $rows, $previousArtifactUuid, $regenerationReason);
    }

    public function generateComparisonCsv(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->comparisonReport($uuid);
        $rows = [
            ['Before/After Comparison Report'],
            ['Report UUID', $data['uuid']],
            ['Overall Risk Reduction', $data['risk_reduction_pct'] . '%'],
            [],
            ['Metric / Segment', 'Baseline Assessment', 'Follow-Up Assessment'],
            ['Date', $data['baseline']['date'], $data['follow_up']['date']],
            ['Ergonomic Risk Score', $data['baseline']['score'], $data['follow_up']['score']],
            ['Risk Severity Level', $data['baseline']['level'], $data['follow_up']['level']],
        ];

        foreach ($data['baseline']['scores'] as $part => $score) {
            $followScore = $data['follow_up']['scores'][$part] ?? 0;
            $rows[] = [ucfirst($part) . ' Posture Score', $score, $followScore];
        }

        $rows[] = [];
        $rows[] = ['Completed Corrective Actions'];
        foreach ($data['completed_actions'] as $action) {
            $rows[] = [$action];
        }

        return $this->writeAndStoreCsv('comparison', $uuid, 'comparison_report_' . $uuid . '_' . date('Ymd_His') . '.csv', 'comparison', $data, $rows, $previousArtifactUuid, $regenerationReason);
    }

    public function generateAuditTrailCsv(string $uuid, ?string $previousArtifactUuid = null, ?string $regenerationReason = null): string
    {
        $data = $this->snapshots->auditTrailReport($uuid);
        $rows = [
            ['Audit Trail Summary Report'],
            ['Report UUID', $data['uuid']],
            ['Generated At', date('Y-m-d H:i:s')],
            [],
            ['Timestamp', 'Authorized User', 'Action', 'Event Details'],
        ];

        foreach ($data['logs'] as $log) {
            $rows[] = [
                $log['timestamp'],
                $log['user'],
                $log['action'],
                $log['details'],
            ];
        }

        return $this->writeAndStoreCsv('audit_trail', $uuid, 'audit_trail_report_' . $uuid . '_' . date('Ymd_His') . '.csv', 'audit_trail', $data, $rows, $previousArtifactUuid, $regenerationReason);
    }

    public function regenerate(
        string $reportType,
        ?string $sourceUuid,
        ?string $organizationUuid = null,
        ?string $previousArtifactUuid = null,
        ?string $regenerationReason = null,
    ): string {
        return match ($reportType) {
            'dashboard' => $this->generateDashboardCsv(),
            'finance' => $this->generateFinanceCsv(),
            'operations' => $this->generateOperationsCsv(),
            'pilot_summary' => $this->generatePilotSummaryCsv($organizationUuid, $previousArtifactUuid, $regenerationReason),
            'assessment' => $this->generateAssessmentCsv($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            'corrective_action' => $this->generateCorrectiveActionCsv($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            'comparison' => $this->generateComparisonCsv($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            'audit_trail' => $this->generateAuditTrailCsv($this->requireSourceUuid($reportType, $sourceUuid), $previousArtifactUuid, $regenerationReason),
            default => throw new \InvalidArgumentException('Unsupported report type for CSV regeneration.'),
        };
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<int, array<int, mixed>> $rows
     */
    private function writeAndStoreCsv(
        string $reportType,
        ?string $sourceUuid,
        string $fileName,
        string $templateName,
        array $snapshot,
        array $rows,
        ?string $previousArtifactUuid = null,
        ?string $regenerationReason = null,
    ): string
    {
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $fp = fopen($tempPath, 'w+');
        if ($fp === false) {
            throw new \RuntimeException('Failed to open temp file for writing CSV.');
        }

        foreach ($rows as $row) {
            $escapedRow = array_map(static fn($val): string => CsvSecurity::value($val), $row);
            fputcsv($fp, $escapedRow);
        }
        fclose($fp);
        $artifactUuid = UuidSupport::generate();
        $actorId = $this->actorId();

        $request = new StoreUploadedFileRequest(
            file: [
                'tmp_name' => $tempPath,
                'name' => $fileName,
                'type' => 'text/csv',
                'size' => filesize($tempPath),
                'error' => UPLOAD_ERR_OK,
            ],
            ownerType: 'reporting',
            ownerUuid: $artifactUuid,
            fieldName: 'csv',
            visibility: 'private',
            actorId: $actorId
        );

        $storedFile = $this->storage->storeUploadedFile($request);
        unlink($tempPath);

        if (!$storedFile) {
            throw new \RuntimeException('Failed to store generated CSV.');
        }

        $this->artifacts->register(
            artifactUuid: $artifactUuid,
            reportType: $reportType,
            sourceUuid: $sourceUuid,
            previousArtifactUuid: $previousArtifactUuid,
            regenerationReason: $regenerationReason,
            format: 'csv',
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
}
