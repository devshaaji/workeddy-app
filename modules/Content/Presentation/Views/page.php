<?php
declare(strict_types=1);

/** @var ?\WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage $page */
/** @var array<string, mixed> $summary */
/** @var ?string $message */
/** @var bool $canEditPage */
/** @var bool $canViewHistory */

$v2Root = dirname(__DIR__, 4);
$pageTitle = (string) ($summary['title'] ?? 'Content Page');
$pagePurpose = 'Platform';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Content', 'url' => '/content'],
    ['label' => (string) ($summary['title'] ?? 'Page'), 'url' => null],
];

$pageActions = [];
if (($canEditPage ?? false) === true) {
    $pageActions[] = [
        'label' => 'Edit Page',
        'url' => '/content/pages/' . rawurlencode((string) ($summary['pageUuid'] ?? '')) . '/edit',
        'class' => 'btn btn-primary',
        'icon' => 'pencil-square',
    ];
}
if (($canViewHistory ?? false) === true) {
    $pageActions[] = [
        'label' => 'History',
        'url' => '/content/pages/' . rawurlencode((string) ($summary['pageUuid'] ?? '')) . '/revisions',
        'class' => 'btn btn-outline-secondary',
        'icon' => 'clock-history',
    ];
}

require $v2Root . '/shared/Views/Partials/page_header.php';

$contentSections = [];
if ($page !== null) {
    foreach ($page->sections as $section) {
        $contentSections[] = [
            'sectionKey' => $section->sectionKey,
            'heading' => $section->heading,
            'blocks' => $section->blocks,
        ];
    }
}
$emptyMessage = $message ?? 'This content page has not been published yet.';
?>

<div class="container-xxl flex-grow-1 py-4 contentManagedPage">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-start">
            <div>
                <div class="small text-muted mb-1">Audience</div>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($summary['audience'] ?? 'internal'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="small text-muted mb-1">Template</div>
                <div class="fw-semibold"><?= htmlspecialchars((string) ($summary['templateKey'] ?? 'internal_default'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="small text-muted mb-1">Published revision</div>
                <code><?= htmlspecialchars((string) ($summary['publishedRevisionUuid'] ?? 'n/a'), ENT_QUOTES, 'UTF-8') ?></code>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/Partials/render_sections.php'; ?>
</div>
