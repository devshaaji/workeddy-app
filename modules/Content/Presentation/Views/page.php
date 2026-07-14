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
$pageCss = ['css/modules/content-page.css'];
$pageScripts = ['js/modules/content-page.js'];

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
$totalWordCount = 0;
if ($page !== null) {
    foreach ($page->sections as $section) {
        $contentSections[] = [
            'sectionKey' => $section->sectionKey,
            'heading' => $section->heading,
            'blocks' => $section->blocks,
            'plainText' => $section->plainText,
        ];
        $totalWordCount += str_word_count($section->plainText);
    }
}

$readingTime = (int) ceil($totalWordCount / 220);
$readingTime = max(1, $readingTime);
$emptyMessage = $message ?? 'This content page has not been published yet.';
$publishedAt = $page?->publishedAt;
?>

<div class="py-4 contentManagedPage">
    <div class="content-article-grid">
        <!-- Left: Main Content Column -->
        <main class="content-article-main">
            <!-- Metadata line -->
            <div class="content-article-metadata d-flex flex-wrap gap-3 align-items-center text-muted small">
                <span>Updated <?= htmlspecialchars($publishedAt?->format('F j, Y') ?? 'recently', ENT_QUOTES, 'UTF-8') ?></span>
                <span>•</span>
                <span><?= $readingTime ?> min read</span>
                <span>•</span>
                <span class="badge bg-label-secondary text-capitalize"><?= htmlspecialchars((string) ($summary['audience'] ?? 'internal'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php require __DIR__ . '/Partials/render_sections.php'; ?>
        </main>

        <!-- Right: Sticky TOC Column -->
        <aside class="content-article-sidebar">
            <div class="content-toc">
                <div class="content-toc-title">On this page</div>
                <ul class="content-toc-list">
                    <?php foreach ($contentSections as $section): ?>
                        <?php 
                            $heading = (string) ($section['heading'] ?? '');
                            $anchor = 'section-' . preg_replace('/[^a-z0-9-]+/i', '-', strtolower($heading));
                        ?>
                        <li>
                            <a href="#<?= htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') ?>" class="content-toc-link">
                                <?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>
    </div>
</div>