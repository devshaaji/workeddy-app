<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>
    </style>
</head>
<body class="report-corrective">
    <div class="header">
        <div class="org-details">
            <strong><?= htmlspecialchars($data['org']['name'] ?? 'WorkEddy') ?></strong><br>
            <?= htmlspecialchars($data['org']['address'] ?? '') ?><br>
            Phone: <?= htmlspecialchars($data['org']['phone'] ?? '') ?> | Email: <?= htmlspecialchars($data['org']['email'] ?? '') ?>
        </div>
        <div class="logo-placeholder">Work<span>Eddy</span></div>
        <div class="clear"></div>
        <div class="title-block">
            <h1>Corrective Action Report</h1>
            <div class="meta">Report UUID: <?= htmlspecialchars($data['uuid']) ?> | Date Generated: <?= date('Y-m-d H:i:s') ?></div>
        </div>
    </div>

    <div class="section-title">Corrective Actions List</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="8%">ID</th>
                <th width="35%">Action Item / Title</th>
                <th width="15%">Assignee</th>
                <th width="15%">Due Date</th>
                <th width="12%">Status</th>
                <th width="15%">Evidence Ref</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['actions'] as $action): 
                $statusClass = 'badge-pending';
                if ($action['status'] === 'Completed') {
                    $statusClass = 'badge-completed';
                } elseif ($action['status'] === 'In Progress') {
                    $statusClass = 'badge-progress';
                }
            ?>
                <tr>
                    <td>#<?= (int)$action['id'] ?></td>
                    <td><strong><?= htmlspecialchars($action['title']) ?></strong></td>
                    <td><?= htmlspecialchars($action['assignee']) ?></td>
                    <td><?= htmlspecialchars($action['due_date']) ?></td>
                    <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($action['status']) ?></span></td>
                    <td><?= htmlspecialchars($action['evidence'] ?? 'None') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="note-box">
        <strong>Overview Note:</strong> Corrective actions are logged and updated periodically to monitor task improvements and verify the implementation of control measures.
    </div>

    <div class="footer">
        Confidential - Generated via WorkEddy Platform. Excludes raw worker identifiers unless authorized.
    </div>
</body>
</html>
