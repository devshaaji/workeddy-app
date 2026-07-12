<?php declare(strict_types=1); ?>
<?php
$pageTitle = 'Audit Trail Summary Report';
$logs = is_array($logs ?? null) ? $logs : [];
$uniqueUsers = [];
$actions = [];
foreach ($logs as $log) {
    $uniqueUsers[(string) ($log['user'] ?? 'Unknown')] = true;
    $actionName = (string) ($log['action'] ?? 'Unknown');
    $actions[$actionName] = ($actions[$actionName] ?? 0) + 1;
}
arsort($actions);
$latestTimestamp = $logs[0]['timestamp'] ?? '--';
?>

<div class="container-xxl flex-grow-1 pb-4">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-secondary mb-2">Audit trail report</span>
                    <h5 class="mb-2">Authorized activity history tied to review, locking, and evidence handling.</h5>
                    <p class="text-muted mb-0">Use this surface to inspect who touched the record, what changed, and when it happened.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/reporting/pilot-summary" class="btn btn-outline-secondary">Back to Pilot Summary</a>
                    <a href="/api/v1/reporting/audit-trail/<?= htmlspecialchars($uuid) ?>/pdf" class="btn btn-primary" target="_blank">Download PDF</a>
                    <a href="/api/v1/reporting/audit-trail/<?= htmlspecialchars($uuid) ?>/csv" class="btn btn-outline-secondary" target="_blank">Export CSV</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-widget-separator-wrapper">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= count($logs) ?></h4>
                                <p class="mb-0">Audit events</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-primary text-heading"><i class="bi bi-journal-text"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= count($uniqueUsers) ?></h4>
                                <p class="mb-0">Unique users</p>
                            </div>
                            <div class="avatar me-lg-6">
                                <span class="avatar-initial rounded bg-label-info text-heading"><i class="bi bi-people"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= count($actions) ?></h4>
                                <p class="mb-0">Action types</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-warning text-heading"><i class="bi bi-diagram-3"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0 text-truncate" style="max-width: 180px;"><?= htmlspecialchars((string) $latestTimestamp) ?></h4>
                                <p class="mb-0">Latest event</p>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-success text-heading"><i class="bi bi-clock-history"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Action Breakdown</h5>
                    <p class="text-muted small mb-0">Most frequent audit actions in this report trail.</p>
                </div>
                <div class="card-body">
                    <?php foreach ($actions as $action => $count): ?>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted"><?= htmlspecialchars($action) ?></span>
                            <strong><?= (int) $count ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($actions === []): ?>
                        <div class="text-muted">No audit events available.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-xl-8">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Authorized Audit Events</h5>
                    <p class="text-muted small mb-0">Detailed chronological log for review, adjustment, and finalization activity.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted"><?= htmlspecialchars((string) ($log['timestamp'] ?? '--')) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars((string) ($log['user'] ?? '--')) ?></td>
                                    <td><span class="badge bg-label-secondary"><?= htmlspecialchars((string) ($log['action'] ?? '--')) ?></span></td>
                                    <td><?= htmlspecialchars((string) ($log['details'] ?? '--')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($logs === []): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No audit events are attached to this report.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header">
            <h5 class="card-title mb-1">Security Note</h5>
            <p class="text-muted small mb-0">Audit reporting should remain complete enough to defend review and access decisions.</p>
        </div>
        <div class="card-body">
            <div class="rounded-3 p-3" style="background: #F8FAFC; border: 1px solid var(--we-border);">
                <div class="fw-semibold mb-1">Operational expectation</div>
                <div class="text-muted small">Review actions, changes, approvals, and evidence access should all remain attributable through this trail.</div>
            </div>
        </div>
    </section>
</div>
