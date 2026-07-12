<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>
    </style>
</head>
<body class="report-operations">
    <div class="header">
        <div class="org-details">
            <strong><?= htmlspecialchars($data['org']['name'] ?? '') ?></strong><br>
            <?= htmlspecialchars($data['org']['address'] ?? '') ?><br>
            Phone: <?= htmlspecialchars($data['org']['phone'] ?? '') ?> | Email: <?= htmlspecialchars($data['org']['email'] ?? '') ?>
        </div>
        <div class="logo-placeholder">Work<span>Eddy</span></div>
        <div class="clear"></div>
        <div class="title-block">
            <h1>Operations Report</h1>
            <div class="meta">Generated: <?= date('Y-m-d H:i:s') ?></div>
        </div>
    </div>

    <div class="section-title">Support Operations</div>
    <table class="data-table">
        <tr>
            <th style="width: 40%;">Open Tickets</th>
            <td><?= (int)($data['ticket_summary']['open_tickets'] ?? 0) ?></td>
        </tr>
    </table>

    <div class="section-title">Installation Operations</div>
    <table class="data-table">
        <tr>
            <th style="width: 40%;">Completed Installations</th>
            <td><?= (int)($data['installation_summary']['completed_installations'] ?? 0) ?></td>
        </tr>
    </table>

    <div class="section-title">Inventory Operations</div>
    <table class="data-table">
        <tr>
            <th style="width: 40%;">Low Stock Items</th>
            <td><?= (int)($data['inventory_summary']['low_stock_items'] ?? 0) ?></td>
        </tr>
    </table>

    <div class="section-title">Human Resources (HR)</div>
    <table class="data-table">
        <tr>
            <th style="width: 40%;">Active Employees</th>
            <td><?= (int)($data['staff_summary']['active_employees'] ?? 0) ?></td>
        </tr>
    </table>

    <div class="section-title">Customer Portfolio</div>
    <table class="data-table">
        <tr>
            <th style="width: 40%;">Total Customers Registered</th>
            <td><?= (int)($data['customer_summary']['total_customers'] ?? 0) ?></td>
        </tr>
        <tr>
            <th style="width: 40%;">Active Customers</th>
            <td><?= (int)($data['customer_summary']['active_customers'] ?? 0) ?></td>
        </tr>
    </table>

    <div class="footer">
        Confidential - Internal Use Only. This report was generated automatically.
    </div>
</body>
</html>
