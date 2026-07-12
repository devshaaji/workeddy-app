<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>
    </style>
</head>
<body class="report-assessment">
    <?php
    $reportScoreStatus = (string) ($data['report_score_status'] ?? 'reviewer_confirmed');
    $scoreReady = $reportScoreStatus === 'reviewer_confirmed';
    $aiAdvisory = is_array($data['ai_advisory'] ?? null) ? $data['ai_advisory'] : [];
    ?>
    <div class="header">
        <div class="org-details">
            <strong><?= htmlspecialchars($data['org']['name'] ?? 'WorkEddy') ?></strong><br>
            <?= htmlspecialchars($data['org']['address'] ?? '') ?><br>
            Phone: <?= htmlspecialchars($data['org']['phone'] ?? '') ?> | Email: <?= htmlspecialchars($data['org']['email'] ?? '') ?>
        </div>
        <div class="logo-placeholder">Work<span>Eddy</span></div>
        <div class="clear"></div>
        <div class="title-block">
            <h1>Ergonomic Assessment Report</h1>
            <div class="meta">Report UUID: <?= htmlspecialchars($data['uuid']) ?> | Date: <?= htmlspecialchars($data['date']) ?> | Template: <?= htmlspecialchars($data['template_version'] ?? 'v1') ?></div>
        </div>
    </div>

    <div class="section-title">Assessment Metadata</div>
    <table class="grid">
        <tr>
            <td width="50%">
                <strong>Worksite:</strong> <?= htmlspecialchars($data['worksite'] ?? '') ?><br>
                <strong>Task Evaluated:</strong> <?= htmlspecialchars($data['task'] ?? '') ?><br>
                <strong>Date Conducted:</strong> <?= htmlspecialchars($data['date'] ?? '') ?><br>
                <strong>Assessment Status:</strong> <?= htmlspecialchars($data['assessment_status'] ?? '') ?><br>
                <strong>Score Source:</strong> <?= htmlspecialchars($data['score_source'] ?? '') ?>
            </td>
            <td width="50%">
                <strong>Assessor:</strong> <?= htmlspecialchars($data['assessor'] ?? '') ?><br>
                <strong>Professional Reviewer:</strong> <?= htmlspecialchars($data['reviewer'] ?? '') ?><br>
                <strong>Risk Category:</strong>
                <span class="badge badge-high">
                    <?= htmlspecialchars($data['risk_level'] ?? '') ?>
                    <?php if ($scoreReady): ?>
                        (Score: <?= (int) ($data['risk_score'] ?? 0) ?>)
                    <?php else: ?>
                        (Awaiting reviewer confirmation)
                    <?php endif; ?>
                </span>
            </td>
        </tr>
    </table>

    <div class="section-title">Postural Risk Heat Map</div>
    <div class="heat-map">
        <div class="heat-map-title">Body Region Exposure Breakdown</div>
        <?php foreach ($data['body_region_scores'] as $part => $score): 
            $fillClass = $score >= 4 ? 'bar-fill-high' : ($score >= 3 ? 'bar-fill-medium' : 'bar-fill-low');
            $pct = min(100, ($score / 5) * 100);
        ?>
            <div class="body-part-bar">
                <span class="body-part-label"><?= ucfirst($part) ?>:</span>
                <div class="bar-container">
                    <div class="bar-fill <?= $fillClass ?>" style="width: <?= $pct ?>%;"></div>
                </div>
                <span class="score-val"><?= $score ?>/5</span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($data['body_region_heatmap']['frontSvg']) || !empty($data['body_region_heatmap']['backSvg'])): ?>
    <div class="section-title">Heat Map Views</div>
    <table class="grid">
        <tr>
            <td width="50%">
                <strong>Front View</strong><br>
                <?= $data['body_region_heatmap']['frontSvg'] ?? '' ?>
            </td>
            <td width="50%">
                <strong>Back View</strong><br>
                <?= $data['body_region_heatmap']['backSvg'] ?? '' ?>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <div class="section-title">Risk Factors & Recommendations</div>
    <table class="grid">
        <tr>
            <td width="50%">
                <strong>Primary Risk Factors:</strong>
                <ul>
                    <?php foreach ($data['risk_factors'] as $factor): ?>
                        <li><?= htmlspecialchars($factor) ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
            <td width="50%">
                <strong>Ergonomic Recommendations:</strong>
                <ul>
                    <?php foreach ($data['recommendations'] as $rec): ?>
                        <li><?= htmlspecialchars($rec) ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
    </table>

    <div class="section-title">Evidence References</div>
    <table class="grid">
        <tr>
            <td width="50%">
                <strong>Thumbnail File UUID:</strong> <?= htmlspecialchars((string) ($data['thumbnail_storage_file_uuid'] ?? '')) ?><br>
                <strong>Pose Video File UUID:</strong> <?= htmlspecialchars((string) ($data['pose_video_storage_file_uuid'] ?? '')) ?>
            </td>
            <td width="50%">
                <strong>Blurred Video File UUID:</strong> <?= htmlspecialchars((string) ($data['blurred_video_storage_file_uuid'] ?? '')) ?><br>
                <strong>Reviewer Notes:</strong> <?= htmlspecialchars((string) ($data['reviewer_notes'] ?? '')) ?><br>
                <strong>Adjustment Reason:</strong> <?= htmlspecialchars((string) ($data['adjustment_reason'] ?? '')) ?>
            </td>
        </tr>
    </table>

    <div class="section-title">AI Advisory Status</div>
    <table class="grid">
        <tr>
            <td width="100%">
                <strong>Advisory Note:</strong> <?= htmlspecialchars((string) ($aiAdvisory['message'] ?? 'AI advisory unavailable.')) ?><br>
                <strong>AI Available:</strong> <?= !empty($aiAdvisory['available']) ? 'Yes' : 'No' ?><br>
                <strong>Model Version:</strong> <?= htmlspecialchars((string) ($aiAdvisory['model_version'] ?? '--')) ?><br>
                <strong>Confidence:</strong> <?= isset($aiAdvisory['confidence']) ? number_format((float) $aiAdvisory['confidence'], 2) : '--' ?>
            </td>
        </tr>
    </table>

    <div class="note-box">
        <strong>Methodology Note:</strong> <?= htmlspecialchars($data['notes']['methodology'] ?? '') ?><br>
        <strong>Limitations Note:</strong> <?= htmlspecialchars($data['notes']['limitations'] ?? '') ?><br>
        <strong>Privacy Note:</strong> <?= htmlspecialchars($data['notes']['privacy'] ?? '') ?>
    </div>

    <div class="footer">
        Confidential - Generated via WorkEddy Platform. Excludes raw worker identifiers unless authorized.
    </div>
</body>
</html>
