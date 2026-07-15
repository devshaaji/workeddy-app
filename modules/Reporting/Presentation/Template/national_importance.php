<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>
        .metric-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .metric-grid td { width: 33.33%; border: 1px solid #d8dee9; padding: 12px; vertical-align: top; }
        .metric-label { font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
        .metric-value { font-size: 22px; font-weight: 700; color: #111827; }
        .prose { font-size: 12px; line-height: 1.6; color: #374151; margin-bottom: 16px; }
        .category-card {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .category-card h4 { margin: 0 0 6px 0; font-size: 12px; color: #1e3a8a; }
        .stat-row { font-size: 10px; color: #374151; margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px dashed #e5e7eb; }
        .stat-row:last-child { border-bottom: none; }
        .stat-value { font-weight: 700; color: #111827; }
        .stat-source { color: #9ca3af; }
        .rank-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .rank-table th, .rank-table td { border: 1px solid #e5e7eb; padding: 6px 10px; font-size: 10px; text-align: left; }
        .rank-table th { background: #f8fafc; text-transform: uppercase; font-size: 9px; color: #6b7280; }
        .no-data { color: #9ca3af; font-style: italic; font-size: 10px; }
        .dynamic-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #1e3a8a;
            background: #dbeafe;
            border: 1px solid #bfdbfe;
            border-radius: 3px;
            padding: 2px 6px;
            margin-bottom: 6px;
        }
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
            <h1>Why WorkEddy Matters for Workforce Health</h1>
            <div class="meta">
                Generated: <?= htmlspecialchars((string) ($data['generated_at'] ?? date('Y-m-d H:i:s'))) ?>
                &mdash; Platform metrics as of <?= htmlspecialchars((string) ($data['dynamic']['generatedAt'] ?? 'not yet computed')) ?>
            </div>
        </div>
    </div>

    <div class="section-title">1. The National Problem</div>
    <p class="prose"><?= nl2br(htmlspecialchars((string) ($data['context']['problemSummary'] ?? ''))) ?></p>

    <div class="section-title">2. National Context by Industry Risk Area</div>
    <p style="font-size: 10px; color: #6b7280; margin-top: -6px; margin-bottom: 10px;">
        Admin-entered national statistics. Every figure below carries its own source citation \u2014 these are public
        reference statistics, not WorkEddy platform data.
    </p>
    <?php foreach (($data['categoryLabels'] ?? []) as $categoryKey => $categoryLabel): ?>
        <?php $stats = $data['statisticsByCategory'][$categoryKey] ?? []; ?>
        <div class="category-card">
            <h4><?= htmlspecialchars((string) $categoryLabel) ?></h4>
            <?php if ($stats === []): ?>
                <div class="no-data">No sourced statistics entered for this topic area yet.</div>
            <?php else: ?>
                <?php foreach ($stats as $stat): ?>
                    <div class="stat-row">
                        <?= htmlspecialchars((string) ($stat['title'] ?? '')) ?>:
                        <span class="stat-value"><?= htmlspecialchars((string) ($stat['value'] ?? '')) ?><?= $stat['unit'] ? ' ' . htmlspecialchars((string) $stat['unit']) : '' ?></span>
                        &mdash;
                        <span class="stat-source">
                            <?= htmlspecialchars((string) ($stat['sourceName'] ?? '')) ?> (<?= htmlspecialchars((string) ($stat['sourceYear'] ?? '')) ?>)
                        </span>
                        <?php if (!empty($stat['updatedAt']) || !empty($stat['dateAdded'])): ?>
                            <div class="stat-source">Updated: <?= htmlspecialchars(substr((string) ($stat['updatedAt'] ?? $stat['dateAdded'] ?? ''), 0, 10)) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="section-title">3. Observed WorkEddy Platform Activity (All Organizations)</div>
    <p style="font-size: 10px; color: #6b7280; margin-top: -6px; margin-bottom: 10px;">
        Aggregated, de-identified activity across every organization on the platform. No individual worksite,
        worker, or organization is identified below.
    </p>
    <table class="metric-grid">
        <tr>
            <td><div class="dynamic-tag">Platform Data</div><div class="metric-label">Industries Represented</div><div class="metric-value"><?= (int) ($data['dynamic']['industriesRepresented'] ?? 0) ?></div></td>
            <td><div class="dynamic-tag">Platform Data</div><div class="metric-label">Worksites Assessed</div><div class="metric-value"><?= (int) ($data['dynamic']['worksitesAssessed'] ?? 0) ?></div></td>
            <td><div class="dynamic-tag">Platform Data</div><div class="metric-label">High-Risk Tasks Identified</div><div class="metric-value"><?= (int) ($data['dynamic']['highRiskTasksIdentified'] ?? 0) ?></div></td>
        </tr>
        <tr>
            <td><div class="dynamic-tag">Platform Data</div><div class="metric-label">Average Risk Reduction After Correction</div><div class="metric-value"><?= number_format((float) ($data['dynamic']['averageRiskReductionAfterCorrection'] ?? 0), 2) ?>%</div></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="section-title" style="font-size: 12px;">Most Common High-Strain Tasks</div>
    <table class="rank-table">
        <thead><tr><th>Task</th><th>Times Flagged High-Risk</th></tr></thead>
        <tbody>
            <?php $highStrainTasks = $data['dynamic']['commonHighStrainTasks'] ?? []; ?>
            <?php if ($highStrainTasks === []): ?>
                <tr><td colspan="2" class="no-data">No data yet.</td></tr>
            <?php else: ?>
                <?php foreach ($highStrainTasks as $row): ?>
                    <tr><td><?= htmlspecialchars((string) ($row['task'] ?? '')) ?></td><td><?= (int) ($row['count'] ?? 0) ?></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title" style="font-size: 12px;">Most Common Body Regions Affected</div>
    <table class="rank-table">
        <thead><tr><th>Body Region</th><th>Observations</th></tr></thead>
        <tbody>
            <?php $bodyRegions = $data['dynamic']['bodyRegionBurden'] ?? []; ?>
            <?php if ($bodyRegions === []): ?>
                <tr><td colspan="2" class="no-data">No data yet.</td></tr>
            <?php else: ?>
                <?php foreach ($bodyRegions as $row): ?>
                    <tr><td><?= htmlspecialchars((string) ($row['region'] ?? '')) ?></td><td><?= (int) ($row['count'] ?? 0) ?></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title" style="font-size: 12px;">Corrective Action Outcomes</div>
    <table class="rank-table">
        <thead><tr><th>Status</th><th>Count</th></tr></thead>
        <tbody>
            <?php $actions = $data['dynamic']['commonCorrectiveActions'] ?? []; ?>
            <?php if ($actions === []): ?>
                <tr><td colspan="2" class="no-data">No data yet.</td></tr>
            <?php else: ?>
                <?php foreach ($actions as $row): ?>
                    <tr><td><?= htmlspecialchars((string) ($row['label'] ?? $row['status'] ?? '')) ?></td><td><?= (int) ($row['count'] ?? 0) ?></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title" style="font-size: 12px;">Worker Discomfort Trend</div>
    <table class="rank-table">
        <thead><tr><th>Month</th><th>Responses</th><th>Average Discomfort</th></tr></thead>
        <tbody>
            <?php $trend = $data['dynamic']['workerDiscomfortTrend'] ?? []; ?>
            <?php if ($trend === []): ?>
                <tr><td colspan="3" class="no-data">No data yet.</td></tr>
            <?php else: ?>
                <?php foreach ($trend as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['month'] ?? '')) ?></td>
                        <td><?= (int) ($row['responses'] ?? 0) ?></td>
                        <td><?= number_format((float) ($row['averageDiscomfort'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">4. Future Research Directions</div>
    <p class="prose"><?= nl2br(htmlspecialchars((string) ($data['context']['futureResearch'] ?? ''))) ?></p>

    <div class="note-box">
        <strong>Methodology.</strong> <?= htmlspecialchars((string) ($data['notes']['methodology'] ?? '')) ?>
    </div>
    <div class="note-box">
        <strong>Limitations.</strong> <?= htmlspecialchars((string) ($data['notes']['limitations'] ?? '')) ?>
    </div>
    <div class="note-box">
        <strong>Privacy.</strong> <?= htmlspecialchars((string) ($data['notes']['privacy'] ?? '')) ?>
    </div>

    <div class="footer">
        Template v<?= htmlspecialchars((string) ($data['template_version'] ?? '1')) ?> &mdash;
        Section 2 (National Context) is public reference data entered and sourced by authorized WorkEddy
        administrators. Section 3 (Observed WorkEddy Platform Activity) is internal, de-identified, aggregated
        platform data. This report does not claim OSHA compliance, guaranteed injury prevention, or eliminated
        risk for any employer or worksite.
    </div>
</body>
</html>
