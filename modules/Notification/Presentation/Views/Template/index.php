<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Message Templates';
$pagePurpose = 'Browse and preview notification content templates.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Notifications', 'url' => null],
    ['label' => 'Templates', 'url' => null],
];
$pageActions = [
    ['label' => 'Logs', 'url' => '/notifications/logs', 'class' => 'btn btn-outline-secondary', 'icon' => 'journal-text'],
    ['label' => 'Settings', 'url' => '/settings/page?module=notification', 'class' => 'btn btn-outline-secondary', 'icon' => 'gear'],
];
$pageScripts = ['js/modules/notification.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="notificationTemplatePage">

    <!-- User context -->
    <div class="d-flex justify-content-end mb-3">
        <span class="badge bg-label-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: var(--we-radius); font-weight: 400;">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars((string) ($userName ?? $userId ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- Info Banner -->
    <div class="alert alert-info d-flex align-items-start gap-3 mb-4" role="note" style="border-radius: var(--we-radius-lg); border-left: 4px solid var(--we-primary);">
        <i class="bi bi-info-circle fs-4 mt-1" style="color: var(--we-primary);"></i>
        <div>
            <strong class="d-block mb-1">Message templates</strong>
            <p class="mb-0 text-secondary">
                Notification templates are loaded from the file system and grouped by event type and channel.
                Use the preview button to render a template with sample data.
            </p>
        </div>
    </div>

    <!-- Templates Table -->
    <section class="card" id="templateRecordsCard" data-endpoint="/api/v1/notification/templates"
             style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">Template registry</h5>
                <p class="text-muted small mb-0">All available notification templates by event type and channel.</p>
            </div>
            <span class="badge bg-label-primary" id="templateResultCount">0 templates</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th data-sort="type">Event type</th>
                        <th data-sort="channel">Channel</th>
                        <th data-sort="filename">Filename</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="templateRecordsBody"></tbody>
            </table>
        </div>
    </section>
</div>

<!-- Template Preview Modal -->
<div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong class="d-block mb-1">Subject</strong>
                    <p class="mb-0" id="templatePreviewSubject">—</p>
                </div>
                <div>
                    <strong class="d-block mb-1">Body</strong>
                    <div id="templatePreviewBody">
                        <p class="text-muted">Select a template to preview.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
