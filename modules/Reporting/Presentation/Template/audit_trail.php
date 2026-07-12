<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>
    </style>
</head>
<body class="report-audit">
    <div class="header">
        <div class="org-details">
            <strong><?= htmlspecialchars($data['org']['name'] ?? 'WorkEddy') ?></strong><br>
            <?= htmlspecialchars($data['org']['address'] ?? '') ?><br>
            Phone: <?= htmlspecialchars($data['org']['phone'] ?? '') ?> | Email: <?= htmlspecialchars($data['org']['email'] ?? '') ?>
        </div>
        <div class="logo-placeholder">Work<span>Eddy</span></div>
        <div class="clear"></div>
        <div class="title-block">
            <h1>Audit Trail Summary Report</h1>
            <div class="meta">Report UUID: <?= htmlspecialchars($data['uuid']) ?> | Date Generated: <?= date('Y-m-d H:i:s') ?></div>
        </div>
    </div>

    <div class="section-title">Audit Log Entries</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="20%">Timestamp</th>
                <th width="20%">Authorized User</th>
                <th width="20%">Action</th>
                <th width="40%">Event Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['logs'] as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['timestamp']) ?></td>
                    <td><strong><?= htmlspecialchars($log['user']) ?></strong></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="note-box">
        <strong>Security & Compliance Note:</strong> The audit trail log tracks all critical review actions, adjustments, and finalizing operations to guarantee data integrity and compliance with industry standards.
    </div>

    <div class="footer">
        Confidential - Generated via WorkEddy Platform. Excludes raw worker identifiers unless authorized.
    </div>
</body>
</html>
