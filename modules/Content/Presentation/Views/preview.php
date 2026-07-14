<?php

declare(strict_types=1);

/** @var array<string, mixed> $summary */
/** @var ?\WorkEddy\Modules\Content\Application\DTOs\ContentPreviewPage $preview */

use WorkEddy\Modules\Content\Support\ContentRichTextRenderer;

$v2Root = dirname(__DIR__, 4);
$pageTitle = (string) ($summary['title'] ?? 'Content Preview');
$pagePurpose = 'Platform';
$pageCss = ['css/modules/content-page.css'];
$pageScripts = ['js/modules/content-page.js'];

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
$references = [];
$referenceIndexByKey = [];
$referenceIndexByTitle = [];
$usedReferenceKeys = [];
$usedReferenceTitles = [];
foreach (($preview->snapshot['references'] ?? []) as $reference) {
    if (!is_array($reference)) {
        continue;
    }
    $references[] = $reference;
}
foreach ($contentSections as $section) {
    $content = is_array($section['content'] ?? null) ? $section['content'] : [];
    if ($content === []) {
        continue;
    }

    $mentions = ContentRichTextRenderer::collectReferenceMentions($content);
    $usedReferenceKeys = array_merge($usedReferenceKeys, $mentions['keys']);
    $usedReferenceTitles = array_merge($usedReferenceTitles, $mentions['titles']);
}
$usedReferenceKeys = array_values(array_unique($usedReferenceKeys));
$usedReferenceTitles = array_values(array_unique($usedReferenceTitles));
$references = array_values(array_filter(
    $references,
    static function (array $reference) use ($usedReferenceKeys, $usedReferenceTitles): bool {
        $referenceKey = trim((string) ($reference['_key'] ?? ''));
        if ($referenceKey !== '' && in_array($referenceKey, $usedReferenceKeys, true)) {
            return true;
        }

        $titleKey = mb_strtolower(trim((string) ($reference['title'] ?? '')));
        return $titleKey !== '' && in_array($titleKey, $usedReferenceTitles, true);
    },
));
foreach ($references as $index => $reference) {
    $number = $index + 1;
    $referenceKey = trim((string) ($reference['_key'] ?? ''));
    if ($referenceKey !== '') {
        $referenceIndexByKey[$referenceKey] = $number;
    }
    $titleKey = mb_strtolower(trim((string) ($reference['title'] ?? '')));
    if ($titleKey !== '' && !isset($referenceIndexByTitle[$titleKey])) {
        $referenceIndexByTitle[$titleKey] = $number;
    }
}

$totalWordCount = 0;
foreach ($contentSections as $sec) {
    if (!empty($sec['plainText'])) {
        $totalWordCount += str_word_count((string) $sec['plainText']);
    } else {
        $heading = $sec['heading'] ?? '';
        $totalWordCount += str_word_count((string) $heading);
        foreach (($sec['blocks'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'paragraph') {
                $totalWordCount += str_word_count((string) ($block['text'] ?? ''));
            } elseif (($block['type'] ?? '') === 'rich_text') {
                $totalWordCount += str_word_count((string) ($block['body'] ?? ''));
            } elseif (($block['type'] ?? '') === 'list') {
                foreach (($block['items'] ?? []) as $item) {
                    $totalWordCount += str_word_count((string) $item);
                }
            }
        }
    }
}

$readingTime = (int) ceil($totalWordCount / 220);
$readingTime = max(1, $readingTime);

$emptyMessage = 'No previewable revision is available.';
?>

<div class="py-4 contentManagedPage">
    <div class="content-article-grid">
        <!-- Left: Main Content Column -->
        <main class="content-article-main">
            <!-- Metadata line -->
            <div class="content-article-metadata d-flex flex-wrap gap-3 align-items-center text-muted small">
                <span>Preview Version v<?= (int) ($preview?->versionNumber ?? 0) ?></span>
                <span>•</span>
                <span><?= $readingTime ?> min read</span>
                <span>•</span>
                <span class="badge bg-label-secondary text-capitalize"><?= htmlspecialchars((string) ($preview?->revisionStatus ?? 'draft'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php require __DIR__ . '/Partials/render_sections.php'; ?>
            <?php require __DIR__ . '/Partials/render_reference_list.php'; ?>
        </main>

        <!-- Right: Table of Contents Column -->
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
