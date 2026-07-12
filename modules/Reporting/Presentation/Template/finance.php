<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?php include __DIR__ . '/report.css'; ?>
    </style>
</head>
<body class="report-finance">
    <div class="header">
        <div class="org-details">
            <strong><?= htmlspecialchars($data['org']['name'] ?? '') ?></strong><br>
            <?= htmlspecialchars($data['org']['address'] ?? '') ?><br>
            Phone: <?= htmlspecialchars($data['org']['phone'] ?? '') ?> | Email: <?= htmlspecialchars($data['org']['email'] ?? '') ?>
        </div>
        <div class="logo-placeholder">Work<span>Eddy</span></div>
        <div class="clear"></div>
        <div class="title-block">
            <h1>Finance Report</h1>
            <div class="meta">Generated: <?= date('Y-m-d H:i:s') ?></div>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-card">
            <h3>Income Total</h3>
            <p>$<?= number_format((float)($data['finance_summary']['income_total'] ?? 0), 2) ?></p>
        </div>
        <div class="summary-card">
            <h3>Expense Total</h3>
            <p>$<?= number_format((float)($data['finance_summary']['expense_total'] ?? 0), 2) ?></p>
        </div>
        <div class="summary-card net">
            <h3>Net Profit / Loss</h3>
            <p>$<?= number_format((float)($data['finance_summary']['income_total'] ?? 0) - (float)($data['finance_summary']['expense_total'] ?? 0), 2) ?></p>
        </div>
        <div class="clear"></div>
    </div>

    <div class="section-title">Income By Category</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['income_by_category'] ?? [] as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($row['category'] ?? '')) ?></td>
                    <td style="text-align: right;">$<?= number_format((float)($row['total'] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Expense By Category</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['expense_by_category'] ?? [] as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($row['category'] ?? '')) ?></td>
                    <td style="text-align: right;">$<?= number_format((float)($row['total'] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Payroll Periods</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Period</th>
                <th style="text-align: right;">Gross Amount</th>
                <th style="text-align: right;">Net Amount</th>
                <th style="text-align: right;">Employee Count</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['payroll_periods'] ?? [] as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($row['period_key'] ?? '')) ?></td>
                    <td style="text-align: right;">$<?= number_format((float)($row['gross_amount'] ?? 0), 2) ?></td>
                    <td style="text-align: right;">$<?= number_format((float)($row['net_amount'] ?? 0), 2) ?></td>
                    <td style="text-align: right;"><?= (int)($row['employee_count'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Confidential - Internal Use Only. This report was generated automatically.
    </div>
</body>
</html>
