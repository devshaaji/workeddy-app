<?php
declare(strict_types=1);

/** @var array<string, mixed> $page */
$v2Root = dirname(__DIR__, 4);
$pageTitle = (string) ($page['title'] ?? 'Revision History');
$pagePurpose = 'Platform';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Content', 'url' => '/content'],
    ['label' => (string) ($page['title'] ?? 'Page'), 'url' => '/content/pages/' . rawurlencode((string) ($page['pageUuid'] ?? ''))],
    ['label' => 'History', 'url' => null],
];
$pageActions = [
    [
        'label' => 'Open Editor',
        'url' => '/content/pages/' . rawurlencode((string) ($page['pageUuid'] ?? '')) . '/edit',
        'class' => 'btn btn-primary',
        'icon' => 'pencil-square',
    ],
];
$pageScripts = ['js/modules/content-history.js'];

require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="contentHistoryPage" data-page-uuid="<?= htmlspecialchars((string) ($page['pageUuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="card" id="contentHistoryCard" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div>
                <h5 class="card-title mb-1">Revision Timeline</h5>
                <p class="text-muted mb-0">Review revision history and restore an older revision into a new draft.</p>
            </div>
            <span class="badge bg-label-primary" id="contentHistoryCountBadge">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="contentRevisionTable">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Summary</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="contentHistoryBody">
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></div>
                            Loading revision history...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted small" id="contentHistory-result-count">0 total records.</div>
    </div>
</div>
