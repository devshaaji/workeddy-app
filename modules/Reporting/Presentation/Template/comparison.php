<?php declare(strict_types=1); ?>
<?php
$baseline = is_array($data['baseline'] ?? null) ? $data['baseline'] : [];
$followUp = is_array($data['follow_up'] ?? null) ? $data['follow_up'] : [];
$correctiveAction = is_array($data['corrective_action_summary'] ?? null) ? $data['corrective_action_summary'] : [];
$completedActions = is_array($data['completed_actions'] ?? null) ? $data['completed_actions'] : [];
$improvedRegions = is_array($data['body_regions_improved'] ?? null) ? $data['body_regions_improved'] : [];
$worsenedRegions = is_array($data['body_regions_worsened'] ?? null) ? $data['body_regions_worsened'] : [];
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$formatDate = static fn(mixed $value): string => $value ? date('F j, Y', strtotime((string) $value)) : '--';
$score = static fn(mixed $value): string => $value === null || $value === '' ? '--' : number_format((float) $value, 0);
$beforeScreenshot = (string) ($baseline['screenshot_data_uri'] ?? '');
$afterScreenshot = (string) ($followUp['screenshot_data_uri'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>

        .comparison-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .comparison-grid td, .comparison-grid th {
            vertical-align: top;
            padding: 10px;
            border: 1px solid #e5e7eb;
        }
        .comparison-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
        }
        .evidence-image {
            width: 100%;
            max-height: 220px;
            object-fit: contain;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }
        .muted {
            color: #6b7280;
        }
        .section-gap {
            margin-top: 18px;
        }
    </style>
</head>
<body class="report-comparison">
    <div class="header">
        <div class="org-details">
            <strong><?= $e($data['org']['name'] ?? 'WorkEddy') ?></strong><br>
            <?= $e($data['org']['address'] ?? '') ?><br>
            Phone: <?= $e($data['org']['phone'] ?? '') ?> | Email: <?= $e($data['org']['email'] ?? '') ?>
        </div>
        <div class="logo-placeholder">Work<span>Eddy</span></div>
        <div class="clear"></div>
        <div class="title-block">
            <h1>Before and After Comparison Report</h1>
            <div class="meta">Report UUID: <?= $e($data['uuid'] ?? '') ?> | Generated: <?= $e($data['generated_at'] ?? date('Y-m-d H:i:s')) ?></div>
        </div>
    </div>

    <table class="comparison-grid">
        <tr>
            <td>
                <div class="comparison-card">
                    <div class="muted">Original task score</div>
                    <div><strong><?= $score($data['original_task_score'] ?? null) ?></strong></div>
                    <div class="muted">Risk level before: <?= $e($data['risk_level_before'] ?? '') ?></div>
                </div>
            </td>
            <td>
                <div class="comparison-card">
                    <div class="muted">Corrected task score</div>
                    <div><strong><?= $score($data['corrected_task_score'] ?? null) ?></strong></div>
                    <div class="muted">Risk level after: <?= $e($data['risk_level_after'] ?? '') ?></div>
                </div>
            </td>
            <td>
                <div class="comparison-card">
                    <div class="muted">Estimated risk reduction</div>
                    <div><strong><?= number_format((float) ($data['risk_reduction_pct'] ?? 0), 1) ?>%</strong></div>
                    <div class="muted">Calculated as ((Original - Corrected) / Original) × 100</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-gap section-title">Baseline and Follow-up Results</div>
    <table class="comparison-grid">
        <tr>
            <td width="50%">
                <h3>Baseline assessment</h3>
                <div class="muted">Date of first assessment: <?= $e($formatDate($baseline['date'] ?? null)) ?></div>
                <div class="muted">Reviewer notes: <?= $e($baseline['reviewer_notes'] ?? 'No reviewer notes captured.') ?></div>
                <?php if ($beforeScreenshot !== ''): ?>
                    <p><img class="evidence-image" src="<?= $beforeScreenshot ?>" alt="Baseline screenshot"></p>
                <?php endif; ?>
                <?php if (!empty($baseline['heatmap']['frontSvg']) || !empty($baseline['heatmap']['backSvg'])): ?>
                    <p><strong>Before heat map</strong></p>
                    <table class="comparison-grid">
                        <tr>
                            <td><?= $baseline['heatmap']['frontSvg'] ?? '' ?></td>
                            <td><?= $baseline['heatmap']['backSvg'] ?? '' ?></td>
                        </tr>
                    </table>
                <?php endif; ?>
            </td>
            <td width="50%">
                <h3>Follow-up assessment</h3>
                <div class="muted">Date of follow up assessment: <?= $e($formatDate($followUp['date'] ?? null)) ?></div>
                <div class="muted">Reviewer notes: <?= $e($followUp['reviewer_notes'] ?? 'No reviewer notes captured.') ?></div>
                <?php if ($afterScreenshot !== ''): ?>
                    <p><img class="evidence-image" src="<?= $afterScreenshot ?>" alt="Follow-up screenshot"></p>
                <?php endif; ?>
                <?php if (!empty($followUp['heatmap']['frontSvg']) || !empty($followUp['heatmap']['backSvg'])): ?>
                    <p><strong>After heat map</strong></p>
                    <table class="comparison-grid">
                        <tr>
                            <td><?= $followUp['heatmap']['frontSvg'] ?? '' ?></td>
                            <td><?= $followUp['heatmap']['backSvg'] ?? '' ?></td>
                        </tr>
                    </table>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="section-gap section-title">Corrective Action Summary</div>
    <table class="comparison-grid">
        <tr>
            <td width="50%">
                <?php if ($correctiveAction !== []): ?>
                    <strong><?= $e($correctiveAction['title'] ?? '') ?></strong><br>
                    Status: <?= $e($correctiveAction['status'] ?? '') ?><br>
                    Completed: <?= $e($formatDate($correctiveAction['completedAt'] ?? null)) ?><br>
                    Verified: <?= $e($formatDate($correctiveAction['verifiedAt'] ?? null)) ?>
                <?php else: ?>
                    No linked corrective action evidence.
                <?php endif; ?>
            </td>
            <td width="50%">
                <strong>Completed actions</strong>
                <?php if ($completedActions === []): ?>
                    <div class="muted">No linked corrective action evidence.</div>
                <?php else: ?>
                    <ul>
                        <?php foreach ($completedActions as $action): ?>
                            <li><?= $e($action) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="section-gap section-title">Body Regions Improved</div>
    <?php if ($improvedRegions === [] && $worsenedRegions === []): ?>
        <p class="muted">No body-region changes recorded.</p>
    <?php else: ?>
        <table class="comparison-grid">
            <thead>
                <tr>
                    <th>Region</th>
                    <th>Baseline Intensity</th>
                    <th>Follow-up Intensity</th>
                    <th>Delta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($improvedRegions as $region): ?>
                    <tr>
                        <td><?= $e((string) ($region['region'] ?? 'Region')) ?> (<?= $e((string) ($region['side'] ?? 'side')) ?>)</td>
                        <td><?= $e((string) ($region['baselineIntensity'] ?? 0)) ?></td>
                        <td><?= $e((string) ($region['followUpIntensity'] ?? 0)) ?></td>
                        <td>Improved</td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($worsenedRegions as $region): ?>
                    <tr>
                        <td><?= $e((string) ($region['region'] ?? 'Region')) ?> (<?= $e((string) ($region['side'] ?? 'side')) ?>)</td>
                        <td><?= $e((string) ($region['baselineIntensity'] ?? 0)) ?></td>
                        <td><?= $e((string) ($region['followUpIntensity'] ?? 0)) ?></td>
                        <td>Worsened</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="note-box">
        <strong>Methodology note:</strong> Estimated risk reduction is calculated as ((Original score - Corrected score) / Original score) × 100. This is a prevention support tool, not a guarantee that injury risk has been eliminated.
    </div>

    <div class="footer">
        Confidential - Generated via WorkEddy Platform. Excludes raw worker identifiers unless authorized.
    </div>
</body>
</html>
