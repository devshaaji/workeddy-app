<?php

declare(strict_types=1);

/** @var ?\WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage $page */
/** @var array<string, mixed> $summary */
/** @var ?string $message */
/** @var bool $canEditPage */
/** @var bool $canViewHistory */

use WorkEddy\Modules\Content\Support\ContentRichTextRenderer;

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
$references = $page?->references ?? [];
$referenceIndexByKey = [];
$referenceIndexByTitle = [];
$usedReferenceKeys = [];
$usedReferenceTitles = [];
if ($page !== null) {
    foreach ($page->sections as $section) {
        $contentSections[] = [
            'sectionKey' => $section->sectionKey,
            'heading' => $section->heading,
            'blocks' => $section->blocks,
            'content' => $section->content,
            'plainText' => $section->plainText,
        ];
        $totalWordCount += str_word_count($section->plainText);

        if ($section->content !== []) {
            $mentions = ContentRichTextRenderer::collectReferenceMentions($section->content);
            $usedReferenceKeys = array_merge($usedReferenceKeys, $mentions['keys']);
            $usedReferenceTitles = array_merge($usedReferenceTitles, $mentions['titles']);
        }
    }
    $usedReferenceKeys = array_values(array_unique($usedReferenceKeys));
    $usedReferenceTitles = array_values(array_unique($usedReferenceTitles));
    $references = array_values(array_filter(
        $references,
        static function (object $reference) use ($usedReferenceKeys, $usedReferenceTitles): bool {
            if ($reference->referenceKey !== null && $reference->referenceKey !== '' && in_array($reference->referenceKey, $usedReferenceKeys, true)) {
                return true;
            }

            $titleKey = mb_strtolower(trim($reference->title));
            return $titleKey !== '' && in_array($titleKey, $usedReferenceTitles, true);
        },
    ));
    foreach ($references as $index => $reference) {
        $number = $index + 1;
        if ($reference->referenceKey !== null && $reference->referenceKey !== '') {
            $referenceIndexByKey[$reference->referenceKey] = $number;
        }
        $titleKey = mb_strtolower(trim($reference->title));
        if ($titleKey !== '' && !isset($referenceIndexByTitle[$titleKey])) {
            $referenceIndexByTitle[$titleKey] = $number;
        }
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
            <?php require __DIR__ . '/Partials/render_reference_list.php'; ?>
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
