<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>
        .metric-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .metric-grid td { width: 33.33%; border: 1px solid #d8dee9; padding: 12px; vertical-align: top; }
        .metric-label { font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
        .metric-value { font-size: 24px; font-weight: 700; color: #111827; }
        .filter-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .filter-grid td { width: 50%; border: 1px solid #e5e7eb; padding: 8px 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="org-details">
            <strong><?= htmlspecialchars($data['org']['name'] ?? 'WorkEddy') ?></strong><br>
            <?= htmlspecialchars($data['org']['address'] ?? '') ?><br>
            Phone: <?= htmlspecialchars($data['org']['phone'] ?? '') ?> | Email: <?= htmlspecialchars($data['org']['email'] ?? '') ?>
        </div>
        <div class="logo-placeholder">Work<span>Eddy</span></div>
        <div class="clear"></div>
        <div class="title-block">
            <h1>Pilot Summary Report</h1>
            <div class="meta">Generated: <?= htmlspecialchars((string) ($data['generated_at'] ?? date('Y-m-d H:i:s'))) ?></div>
        </div>
    </div>

    <div class="section-title">Applied Filters</div>
    <table class="filter-grid">
        <tr><td>Industry</td><td><?= htmlspecialchars((string) ($data['filters']['industry'] ?? '')) ?></td></tr>
        <tr><td>Worksite UUID</td><td><?= htmlspecialchars((string) ($data['filters']['worksiteUuid'] ?? '')) ?></td></tr>
        <tr><td>Department UUID</td><td><?= htmlspecialchars((string) ($data['filters']['departmentUuid'] ?? '')) ?></td></tr>
        <tr><td>Job Role UUID</td><td><?= htmlspecialchars((string) ($data['filters']['jobRoleUuid'] ?? '')) ?></td></tr>
        <tr><td>Body Region</td><td><?= htmlspecialchars((string) ($data['filters']['bodyRegion'] ?? '')) ?></td></tr>
        <tr><td>From Date</td><td><?= htmlspecialchars((string) ($data['filters']['fromDate'] ?? '')) ?></td></tr>
        <tr><td>To Date</td><td><?= htmlspecialchars((string) ($data['filters']['toDate'] ?? '')) ?></td></tr>
        <tr><td>Risk Level</td><td><?= htmlspecialchars((string) ($data['filters']['riskLevel'] ?? '')) ?></td></tr>
    </table>

    <div class="section-title">Implementation Progress</div>
    <table class="metric-grid">
        <tr>
            <td><div class="metric-label">Worksites Enrolled</div><div class="metric-value"><?= (int) ($data['summary']['worksites_enrolled'] ?? 0) ?></div></td>
            <td><div class="metric-label">Workers Participating</div><div class="metric-value"><?= (int) ($data['summary']['workers_participating'] ?? 0) ?></div></td>
            <td><div class="metric-label">Task Videos Uploaded</div><div class="metric-value"><?= (int) ($data['summary']['task_videos_uploaded'] ?? 0) ?></div></td>
        </tr>
        <tr>
            <td><div class="metric-label">Assessments Completed</div><div class="metric-value"><?= (int) ($data['summary']['assessments'] ?? 0) ?></div></td>
            <td><div class="metric-label">Corrective Actions Assigned</div><div class="metric-value"><?= (int) ($data['summary']['corrective_actions_assigned'] ?? 0) ?></div></td>
            <td><div class="metric-label">Corrective Actions Completed</div><div class="metric-value"><?= (int) ($data['summary']['corrective_actions_completed'] ?? 0) ?></div></td>
        </tr>
        <tr>
            <td><div class="metric-label">Corrective Actions Overdue</div><div class="metric-value"><?= (int) ($data['summary']['corrective_actions_overdue'] ?? 0) ?></div></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="section-title">Outcome Metrics</div>
    <table class="metric-grid">
        <tr>
            <td><div class="metric-label">High Risk Tasks Identified</div><div class="metric-value"><?= (int) ($data['summary']['high_risk_tasks_identified'] ?? 0) ?></div></td>
            <td><div class="metric-label">Comparison Reports</div><div class="metric-value"><?= (int) ($data['summary']['comparison_reports'] ?? 0) ?></div></td>
            <td><div class="metric-label">Average Closure Days</div><div class="metric-value"><?= number_format((float) ($data['summary']['average_closure_days'] ?? 0), 2) ?></div></td>
        </tr>
        <tr>
            <td><div class="metric-label">Average Risk Reduction %</div><div class="metric-value"><?= number_format((float) ($data['summary']['average_risk_reduction_pct'] ?? 0), 2) ?></div></td>
            <td><div class="metric-label">Average Discomfort</div><div class="metric-value"><?= number_format((float) ($data['summary']['average_discomfort'] ?? 0), 2) ?></div></td>
            <td><div class="metric-label">Reviewer Agreement Rate %</div><div class="metric-value"><?= number_format((float) ($data['summary']['reviewer_agreement_rate'] ?? 0), 2) ?></div></td>
        </tr>
    </table>

    <div class="section-title">Top Body Regions</div>
    <table class="comparison-table">
        <thead>
            <tr>
                <th class="left-align">Body Region</th>
                <th>Responses</th>
                <th>Average Discomfort</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($data['top_body_regions'] ?? []) as $item): ?>
                <tr>
                    <td class="left-align"><?= htmlspecialchars((string) ($item['bodyRegion'] ?? 'Unknown')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['responses'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($item['averageDiscomfort'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Top Tasks</div>
    <table class="comparison-table">
        <thead>
            <tr>
                <th class="left-align">Task</th>
                <th>Responses</th>
                <th>Average Discomfort</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($data['top_tasks'] ?? []) as $item): ?>
                <tr>
                    <td class="left-align"><?= htmlspecialchars((string) ($item['taskName'] ?? 'Unlinked')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['responses'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($item['averageDiscomfort'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Feedback Timeline</div>
    <table class="comparison-table">
        <thead>
            <tr>
                <th class="left-align">Date</th>
                <th>Responses</th>
                <th>Average Discomfort</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($data['timeline'] ?? []) as $item): ?>
                <tr>
                    <td class="left-align"><?= htmlspecialchars((string) ($item['date'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['responses'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($item['averageDiscomfort'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Supervisor Body Region Trends</div>
    <table class="comparison-table">
        <thead>
            <tr>
                <th class="left-align">Body Region</th>
                <th>Observations</th>
                <th>Average Severity</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($data['supervisor_top_body_regions'] ?? []) as $item): ?>
                <tr>
                    <td class="left-align"><?= htmlspecialchars((string) ($item['bodyRegion'] ?? 'Unspecified')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['responses'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($item['averageSeverity'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Supervisor Feedback Timeline</div>
    <table class="comparison-table">
        <thead>
            <tr>
                <th class="left-align">Date</th>
                <th>Observations</th>
                <th>Average Severity</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($data['supervisor_timeline'] ?? []) as $item): ?>
                <tr>
                    <td class="left-align"><?= htmlspecialchars((string) ($item['date'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['responses'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($item['averageSeverity'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Validation Agreement</div>
    <table class="comparison-table">
        <thead>
            <tr>
                <th class="left-align">Metric</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr><td class="left-align">Overall Agreement Rate</td><td><?= htmlspecialchars((string) ($data['validation_agreement']['overallAgreementRate'] ?? 0)) ?>%</td></tr>
            <tr><td class="left-align">Risk Level Agreement Rate</td><td><?= htmlspecialchars((string) ($data['validation_agreement']['riskLevelAgreementRate'] ?? 0)) ?>%</td></tr>
            <tr><td class="left-align">Score Agreement Rate</td><td><?= htmlspecialchars((string) ($data['validation_agreement']['scoreAgreementRate'] ?? 0)) ?>%</td></tr>
            <tr><td class="left-align">Body Region Agreement Rate</td><td><?= htmlspecialchars((string) ($data['validation_agreement']['bodyRegionAgreementRate'] ?? 0)) ?>%</td></tr>
            <tr><td class="left-align">Review Pair Count</td><td><?= htmlspecialchars((string) ($data['validation_agreement']['pairCount'] ?? 0)) ?></td></tr>
        </tbody>
    </table>
</body>
</html>
