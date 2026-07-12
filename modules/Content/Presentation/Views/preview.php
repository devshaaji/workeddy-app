<?php
declare(strict_types=1);

/** @var array<string, mixed> $summary */
/** @var ?\WorkEddy\Modules\Content\Application\DTOs\ContentPreviewPage $preview */

$v2Root = dirname(__DIR__, 4);
$pageTitle = (string) ($summary['title'] ?? 'Content Preview');
$pagePurpose = 'Platform';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Content', 'url' => '/content'],
    ['label' => (string) ($summary['title'] ?? 'Page'), 'url' => '/content/pages/' . rawurlencode((string) ($summary['pageUuid'] ?? ''))],
    ['label' => 'Preview', 'url' => null],
];
$pageActions = [
    [
        'label' => 'Back to Editor',
        'url' => '/content/pages/' . rawurlencode((string) ($summary['pageUuid'] ?? '')) . '/edit',
        'class' => 'btn btn-primary',
        'icon' => 'pencil-square',
    ],
];

require $v2Root . '/shared/Views/Partials/page_header.php';

$contentSections = $preview !== null && is_array($preview->snapshot['sections'] ?? null) ? array_values($preview->snapshot['sections']) : [];
$emptyMessage = 'No previewable revision is available.';
?>

<div class="container-xxl flex-grow-1 py-4 contentManagedPage">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-start">
            <div>
                <div class="small text-muted mb-1">Revision status</div>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($preview?->revisionStatus ?? 'unavailable'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="small text-muted mb-1">Version</div>
                <div class="fw-semibold">v<?= (int) ($preview?->versionNumber ?? 0) ?></div>
            </div>
            <div>
                <div class="small text-muted mb-1">Revision UUID</div>
                <code><?= htmlspecialchars((string) ($preview?->revisionUuid ?? 'n/a'), ENT_QUOTES, 'UTF-8') ?></code>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/Partials/render_sections.php'; ?>
</div>
