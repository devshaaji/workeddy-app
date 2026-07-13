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
        .section-title.observed { color: #065f46; }
        .section-title.estimated { color: #92400e; }
        .metric-grid.estimated td { background: #fffbeb; border-color: #fde68a; }
        .estimate-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 3px;
            padding: 2px 6px;
            margin-bottom: 6px;
        }
        .observed-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #065f46;
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            border-radius: 3px;
            padding: 2px 6px;
            margin-bottom: 6px;
        }
        .disclaimer-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #d97706;
            border-radius: 4px;
            padding: 14px;
            margin: 10px 0 20px 0;
            font-size: 11px;
            color: #78350f;
        }
        .disclaimer-box strong { color: #92400e; }
        .assumption-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .assumption-table td { border: 1px solid #fde68a; padding: 6px 10px; font-size: 10px; color: #78350f; }
        .assumption-table td.label { background: #fffbeb; font-weight: 700; width: 60%; }
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
            <h1>Public Health Impact Tracker &mdash; Preliminary Platform Findings</h1>
            <div class="meta">Generated: <?= htmlspecialchars((string) ($data['generated_at'] ?? date('Y-m-d H:i:s'))) ?></div>
        </div>
    </div>

    <div class="disclaimer-box">
        <strong>How to read this report:</strong> Section 1 (Observed Platform Activity) is counted directly from
        recorded assessments, corrective actions, comparison reports, worker feedback, tasks, and worksites &mdash;
        no assumptions involved. Section 2 (Estimated Impact) applies disclosed, editable assumption rates to the
        observed figures to produce planning approximations. <?= htmlspecialchars((string) ($data['disclaimer'] ?? '')) ?>
        These estimates are not guarantees of injuries prevented, workdays saved, costs avoided, or regulatory
        compliance of any kind.
    </div>

    <div class="section-title observed">1. Observed Platform Activity</div>
    <p style="font-size: 11px; color: #4b5563; margin-top: -6px; margin-bottom: 12px;">
        Counted directly from platform records. No projections applied.
    </p>
    <table class="metric-grid">
        <tr>
            <td><div class="observed-tag">Observed</div><div class="metric-label">High-Risk Tasks Identified</div><div class="metric-value"><?= (int) ($data['observed']['high_risk_tasks_identified'] ?? 0) ?></div></td>
            <td><div class="observed-tag">Observed</div><div class="metric-label">High-Risk Tasks Reduced</div><div class="metric-value"><?= (int) ($data['observed']['high_risk_tasks_reduced'] ?? 0) ?></div></td>
            <td><div class="observed-tag">Observed</div><div class="metric-label">Corrective Actions Completed</div><div class="metric-value"><?= (int) ($data['observed']['corrective_actions_completed'] ?? 0) ?></div></td>
        </tr>
        <tr>
            <td><div class="observed-tag">Observed</div><div class="metric-label">Average Risk Reduction</div><div class="metric-value"><?= number_format((float) ($data['observed']['average_risk_reduction_pct'] ?? 0), 2) ?>%</div></td>
            <td><div class="observed-tag">Observed</div><div class="metric-label">Departments Improved</div><div class="metric-value"><?= (int) ($data['observed']['departments_improved'] ?? 0) ?></div></td>
            <td><div class="observed-tag">Observed</div><div class="metric-label">Workers Reached</div><div class="metric-value"><?= (int) ($data['observed']['workers_reached'] ?? 0) ?></div></td>
        </tr>
        <tr>
            <td><div class="observed-tag">Observed</div><div class="metric-label">Repeat High-Risk Tasks</div><div class="metric-value"><?= (int) ($data['observed']['repeat_high_risk_tasks'] ?? 0) ?></div>
                <div style="font-size: 9px; color: #6b7280; margin-top: 4px;">Tasks flagged High Risk more than once &mdash; a caution signal, not a success metric.</div>
            </td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="section-title estimated">2. Estimated Impact (Planning Approximations &mdash; Not a Guarantee)</div>
    <p style="font-size: 11px; color: #78350f; margin-top: -6px; margin-bottom: 12px;">
        Derived from the observed "High-Risk Tasks Reduced" figure above multiplied by the disclosed assumption
        rates listed below. Every figure in this section is a potential estimate, not a confirmed or measured outcome.
    </p>
    <table class="metric-grid estimated">
        <tr>
            <td><div class="estimate-tag">Estimate</div><div class="metric-label">Potential Injuries Prevented</div><div class="metric-value"><?= number_format((float) ($data['estimated']['potential_injuries_prevented'] ?? 0), 2) ?></div></td>
            <td><div class="estimate-tag">Estimate</div><div class="metric-label">Potential Lost Workdays Avoided</div><div class="metric-value"><?= number_format((float) ($data['estimated']['potential_lost_workdays_avoided'] ?? 0), 1) ?></div></td>
            <td><div class="estimate-tag">Estimate</div><div class="metric-label">Potential Cost Savings</div><div class="metric-value">$<?= number_format((float) ($data['estimated']['potential_cost_savings'] ?? 0), 2) ?></div></td>
        </tr>
    </table>

    <div class="section-title estimated" style="font-size: 11px;">Estimate Methodology &mdash; Disclosed Assumptions</div>
    <table class="assumption-table">
        <tr><td class="label">Assumed injury-prevention rate per resolved high-risk task</td><td><?= number_format((float) ($data['assumptions']['injuryPreventionRate'] ?? 0) * 100, 1) ?>%</td></tr>
        <tr><td class="label">Assumed lost workdays per potential injury</td><td><?= number_format((float) ($data['assumptions']['lostWorkdaysPerInjury'] ?? 0), 1) ?> days</td></tr>
        <tr><td class="label">Assumed fully-burdened cost per lost workday</td><td>$<?= number_format((float) ($data['assumptions']['costPerLostWorkday'] ?? 0), 2) ?></td></tr>
    </table>
    <p style="font-size: 10px; color: #9ca3af; margin-top: -4px;">
        These assumption rates are configurable by your organization and reflect a conservative planning methodology,
        not a scientific measurement of this pilot's actual health outcomes.
    </p>

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
        This report distinguishes observed platform activity from estimated impact. Estimated figures are
        preliminary planning approximations and must not be represented as guaranteed injury prevention,
        confirmed cost savings, eliminated risk, or regulatory/OSHA compliance.
    </div>
</body>
</html>
