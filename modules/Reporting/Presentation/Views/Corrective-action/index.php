<?php declare(strict_types=1); ?>
<?php
$pageTitle = 'Corrective Action Report';
$actions = is_array($actions ?? null) ? $actions : [];
$completedCount = 0;
$inProgressCount = 0;
$openCount = 0;
$evidenceCount = 0;

foreach ($actions as $action) {
    $status = (string) ($action['status'] ?? '');
    if ($status === 'Completed') {
        $completedCount++;
    } elseif ($status === 'In Progress') {
        $inProgressCount++;
    } else {
        $openCount++;
    }

    $evidence = trim((string) ($action['evidence'] ?? ''));
    if ($evidence !== '' && strtolower($evidence) !== 'none') {
        $evidenceCount++;
    }
}
?>

<div class="container-xxl flex-grow-1 pb-4">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-success mb-2">Corrective action report</span>
                    <h5 class="mb-2">Remediation record with assignment, due dates, status movement, and evidence posture.</h5>
                    <p class="text-muted mb-0">This page keeps action execution and reporting status in the same working surface.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/reporting/pilot-summary" class="btn btn-outline-secondary">Back to Pilot Summary</a>
                    <a href="/api/v1/reporting/corrective-action/<?= htmlspecialchars($uuid) ?>/pdf" class="btn btn-primary" target="_blank">Download PDF</a>
                    <a href="/api/v1/reporting/corrective-action/<?= htmlspecialchars($uuid) ?>/csv" class="btn btn-outline-secondary" target="_blank">Export CSV</a>
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
                                <h4 class="mb-0"><?= count($actions) ?></h4>
                                <p class="mb-0">Total actions</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-primary text-heading"><i class="bi bi-list-check"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= $completedCount ?></h4>
                                <p class="mb-0">Completed</p>
                            </div>
                            <div class="avatar me-lg-6">
                                <span class="avatar-initial rounded bg-label-success text-heading"><i class="bi bi-check2-circle"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= $inProgressCount ?></h4>
                                <p class="mb-0">In progress</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-warning text-heading"><i class="bi bi-hourglass-split"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= $evidenceCount ?></h4>
                                <p class="mb-0">With evidence</p>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-info text-heading"><i class="bi bi-paperclip"></i></span>
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
                    <h5 class="card-title mb-1">Execution Snapshot</h5>
                    <p class="text-muted small mb-0">Status distribution for the corrective action set tied to this report.</p>
                </div>
                <div class="card-body">
                    <?php
                    $totalActions = max(1, count($actions));
                    $statusRows = [
                        ['label' => 'Open', 'value' => $openCount, 'class' => 'bg-danger'],
                        ['label' => 'In Progress', 'value' => $inProgressCount, 'class' => 'bg-warning'],
                        ['label' => 'Completed', 'value' => $completedCount, 'class' => 'bg-success'],
                    ];
                    ?>
                    <?php foreach ($statusRows as $row): ?>
                        <?php $pct = ((int) $row['value'] / $totalActions) * 100; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small text-muted"><?= $row['label'] ?></span>
                                <span class="fw-semibold"><?= (int) $row['value'] ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?= $row['class'] ?>" style="width: <?= $pct ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <div class="col-xl-8">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Corrective Action Log</h5>
                    <p class="text-muted small mb-0">Working list of remediation items, owners, timing, and evidence references.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Action</th>
                                <th>Assignee</th>
                                <th>Due date</th>
                                <th>Status</th>
                                <th>Evidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actions as $action): ?>
                                <?php
                                $status = (string) ($action['status'] ?? '');
                                $statusClass = 'bg-label-danger';
                                if ($status === 'Completed') {
                                    $statusClass = 'bg-label-success';
                                } elseif ($status === 'In Progress') {
                                    $statusClass = 'bg-label-warning';
                                }
                                ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int) ($action['id'] ?? 0) ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars((string) ($action['title'] ?? 'Untitled action')) ?></td>
                                    <td><?= htmlspecialchars((string) ($action['assignee'] ?? '--')) ?></td>
                                    <td><?= htmlspecialchars((string) ($action['due_date'] ?? '--')) ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status !== '' ? $status : 'Open') ?></span></td>
                                    <td class="text-muted"><?= htmlspecialchars((string) ($action['evidence'] ?? 'None')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($actions === []): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No corrective actions are attached to this report.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header">
            <h5 class="card-title mb-1">Reporting Note</h5>
            <p class="text-muted small mb-0">Action updates here should stay aligned with operational logs and evidence uploads.</p>
        </div>
        <div class="card-body">
            <div class="rounded-3 p-3" style="background: #F8FAFC; border: 1px solid var(--we-border);">
                <div class="fw-semibold mb-1">Audit linkage</div>
                <div class="text-muted small">Changes to assignment, due date, status, or evidence should remain traceable through audit activity and follow-up reporting.</div>
            </div>
        </div>
    </section>
</div>
