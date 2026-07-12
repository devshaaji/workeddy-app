<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Notification Logs';
$pagePurpose = 'Review delivery status and retry failed notifications.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Notifications', 'url' => null],
    ['label' => 'Logs', 'url' => null],
];
$pageActions = [
    ['label' => 'Templates', 'url' => '/notifications/templates', 'class' => 'btn btn-outline-secondary', 'icon' => 'file-text'],
    ['label' => 'Settings', 'url' => '/notifications/settings', 'class' => 'btn btn-outline-secondary', 'icon' => 'gear'],
];
$pageScripts = ['js/modules/notification.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="notificationLogPage">

    <!-- User context -->
    <div class="d-flex justify-content-end mb-3">
        <span class="badge bg-label-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: var(--we-radius); font-weight: 400;">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars((string) ($userName ?? $userId ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- Stats Row -->
    <section class="row g-4 mb-4" aria-label="Delivery summary">
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Total events</span>
                            <h3 class="mb-1 fw-bold" id="logStatTotal">—</h3>
                            <small class="text-muted">All records</small>
                        </div>
                        <span class="rounded p-2" style="background: var(--we-primary-light)">
                            <i class="bi bi-send fs-4" style="color: var(--we-primary)"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Sent</span>
                            <h3 class="mb-1 fw-bold" id="logStatSent">—</h3>
                            <small class="text-muted">Delivered</small>
                        </div>
                        <span class="rounded p-2 bg-label-success">
                            <i class="bi bi-check2-circle fs-4"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Failed</span>
                            <h3 class="mb-1 fw-bold" id="logStatFailed">—</h3>
                            <small class="text-muted">Needs attention</small>
                        </div>
                        <span class="rounded p-2 bg-label-danger">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Queued</span>
                            <h3 class="mb-1 fw-bold" id="logStatQueued">—</h3>
                            <small class="text-muted">Pending dispatch</small>
                        </div>
                        <span class="rounded p-2 bg-label-info">
                            <i class="bi bi-hourglass-split fs-4"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Filters -->
    <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="logSearch" class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="logSearch" class="form-control" type="search" placeholder="Type, recipient, reason...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="logChannelFilter" class="form-label">Channel</label>
                    <select id="logChannelFilter" class="form-select">
                        <option value="">All channels</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="logStatusFilter" class="form-label">Status</label>
                    <select id="logStatusFilter" class="form-select">
                        <option value="">All statuses</option>
                        <option value="queued">Queued</option>
                        <option value="sent">Sent</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-outline-secondary cursor-pointer" type="button" id="logClearFilters">
                        <i class="bi bi-x-circle me-1"></i>Clear filters
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Delivery Records Table -->
    <section class="card" id="logRecordsCard" data-endpoint="/api/v1/notification/logs"
             style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">Delivery records</h5>
                <p class="text-muted small mb-0">Outbound notification dispatch log with status and failure details.</p>
            </div>
            <span class="badge bg-label-primary" id="logResultCount">0 records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th data-sort="notificationType">Type</th>
                        <th data-sort="channel">Channel</th>
                        <th data-sort="recipientName">Recipient</th>
                        <th data-sort="status">Status</th>
                        <th data-sort="attemptCount">Attempts</th>
                        <th data-sort="failureReason">Failure reason</th>
                        <th data-sort="createdAt">Timestamp</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="logRecordsBody"></tbody>
            </table>
        </div>
    </section>
</div>

<!-- Log Detail Modal -->
<div class="modal fade" id="notificationLogDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Notification delivery detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="logDetailBody">
                <p class="text-muted">Select a record to view details.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
