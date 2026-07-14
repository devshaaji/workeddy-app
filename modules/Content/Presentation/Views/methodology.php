<?php

declare(strict_types=1);

/** @var ?\WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage $page */
/** @var array<string, mixed> $summary */
/** @var ?string $message */
/** @var bool $canEditPage */
/** @var bool $canViewHistory */

use WorkEddy\Modules\Content\Support\ContentRichTextRenderer;

$v2Root = dirname(__DIR__, 4);
$pageTitle = (string) ($summary['title'] ?? 'Methodology and Limitations');
$pagePurpose = 'Platform';
$pageCss = ['css/methodology-page.css'];
$pageScripts = ['js/modules/methodology-page.js'];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Methodology', 'url' => null],
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
            'content' => $section->content,
            'plainText' => $section->plainText,
        ];
    }
}

$emptyMessage = $message ?? 'This content page has not been published yet.';
$references = $page?->references ?? [];
$referenceIndexByKey = [];
$referenceIndexByTitle = [];
$usedReferenceKeys = [];
$usedReferenceTitles = [];
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

$introSection = $contentSections[0] ?? null;
$closingSection = count($contentSections) > 1 ? $contentSections[array_key_last($contentSections)] : null;
$middleSections = count($contentSections) > 2 ? array_slice($contentSections, 1, -1) : [];
if (count($contentSections) === 2) {
    $middleSections = [$contentSections[1]];
}

$navSections = $contentSections;
$introText = $introSection['plainText'] ?? 'WorkEddy explains how it measures ergonomic risk, where human review matters, what the platform does not claim, and how privacy and prevention evidence are handled.';
$publishedDate = $page?->publishedAt?->format('F j, Y');
$wordCount = str_word_count(trim(implode(' ', array_map(static fn(array $section): string => (string) ($section['plainText'] ?? ''), $contentSections))));

$renderBlocks = static function (array $blocks, ?array $content = null) use ($referenceIndexByKey, $referenceIndexByTitle): void {
    if (is_array($content) && (($content['format'] ?? null) === 'quill_delta')) {
        echo '<div class="content-richtext-body">';
        echo ContentRichTextRenderer::render($content, $referenceIndexByKey, $referenceIndexByTitle);
        echo '</div>';
        return;
    }

    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? '');
        if ($type === 'paragraph') {
            echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
            continue;
        }
        if ($type === 'rich_text') {
            echo '<div class="content-richtext-body">' . (string) ($block['body'] ?? '') . '</div>';
            continue;
        }
        if ($type === 'list') {
            echo '<ul class="methodology-tree-list">';
            foreach (($block['items'] ?? []) as $item) {
                echo '<li>' . htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
            continue;
        }
        if ($type === 'image') {
            $storageFileUuid = trim((string) ($block['storageFileUuid'] ?? ''));
            $previewUrl = trim((string) ($block['previewUrl'] ?? ''));
            $imageSrc = $previewUrl !== '' ? $previewUrl : ($storageFileUuid !== '' ? '/api/v1/storage/files/' . rawurlencode($storageFileUuid) . '/view' : '');
            if ($imageSrc !== '') {
                echo '<figure class="content-image-embed">';
                echo '<img class="content-image-embed__image" src="' . htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) ($block['altText'] ?? ''), ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
                if (!empty($block['caption']) || !empty($block['altText'])) {
                    echo '<figcaption class="content-image-embed__caption">';
                    if (!empty($block['caption'])) {
                        echo '<div class="content-image-embed__title">' . htmlspecialchars((string) $block['caption'], ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    if (!empty($block['altText'])) {
                        echo '<div class="content-image-embed__meta">' . htmlspecialchars((string) $block['altText'], ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    echo '</figcaption>';
                }
                echo '</figure>';
                continue;
            }
            echo '<div class="methodology-inline-note">';
            echo '<div class="fw-semibold mb-1">Illustration</div>';
            echo '<div class="small text-muted">Media UUID: ' . htmlspecialchars((string) ($block['mediaUuid'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
            if (!empty($block['caption'])) {
                echo '<div class="small text-muted mt-2">' . htmlspecialchars((string) $block['caption'], ENT_QUOTES, 'UTF-8') . '</div>';
            }
            echo '</div>';
        }
    }
};
?>

<main class="methodology-tree-page contentManagedPage">
    <div class="container-xxl">
        <?php if ($contentSections === []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-muted">
                    <?= htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        <?php else: ?>
            <section class="methodology-tree-hero">
                <p class="lead text-body-secondary mb-0 methodology-tree-intro"><?= htmlspecialchars($introText, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($publishedDate !== null || $wordCount > 0): ?>
                    <div class="d-flex flex-wrap gap-2 small text-muted pt-3 mt-4 border-top methodology-tree-meta">
                        <?php if ($publishedDate !== null): ?>
                            <span><?= htmlspecialchars($publishedDate, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($wordCount > 0): ?>
                            <span><?= (int) ceil($wordCount / 220) ?> min read</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="methodology-tree-shell">
                <aside class="methodology-tree-nav" aria-label="Methodology navigation">
                    <div class="small text-uppercase fw-semibold text-muted mb-3 methodology-tree-nav-label">Contents</div>
                    <nav class="methodology-tree-branches">
                        <?php foreach ($navSections as $section): ?>
                            <?php $anchor = 'methodology-' . preg_replace('/[^a-z0-9-]+/i', '-', (string) ($section['sectionKey'] ?? '')); ?>
                            <a href="#<?= htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') ?>" class="methodology-tree-branch">
                                <span class="methodology-tree-node"></span>
                                <span><?= htmlspecialchars((string) ($section['heading'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </aside>

                <article class="methodology-tree-content">
                    <?php if ($introSection !== null): ?>
                        <?php $introAnchor = 'methodology-' . preg_replace('/[^a-z0-9-]+/i', '-', (string) ($introSection['sectionKey'] ?? '')); ?>
                        <section id="<?= htmlspecialchars($introAnchor, ENT_QUOTES, 'UTF-8') ?>" class="methodology-tree-opening">
                            <h2 class="h4 fw-semibold mb-3"><?= htmlspecialchars((string) ($introSection['heading'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php $renderBlocks($introSection['blocks'] ?? [], is_array($introSection['content'] ?? null) ? $introSection['content'] : null); ?>
                        </section>
                    <?php endif; ?>

                    <?php foreach ($middleSections as $section): ?>
                        <?php
                        $sectionKey = (string) ($section['sectionKey'] ?? '');
                        $anchor = 'methodology-' . preg_replace('/[^a-z0-9-]+/i', '-', $sectionKey);
                        ?>
                        <section id="<?= htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') ?>" class="methodology-tree-section">
                            <h2 class="h5 fw-semibold mb-3"><?= htmlspecialchars((string) ($section['heading'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php $renderBlocks($section['blocks'] ?? [], is_array($section['content'] ?? null) ? $section['content'] : null); ?>
                        </section>
                    <?php endforeach; ?>

                    <?php if ($closingSection !== null && $closingSection !== $introSection): ?>
                        <?php
                        $closingKey = (string) ($closingSection['sectionKey'] ?? '');
                        $closingAnchor = 'methodology-' . preg_replace('/[^a-z0-9-]+/i', '-', $closingKey);
                        ?>
                        <section id="<?= htmlspecialchars($closingAnchor, ENT_QUOTES, 'UTF-8') ?>" class="methodology-tree-closing">
                            <h2 class="h4 fw-semibold mb-3"><?= htmlspecialchars((string) ($closingSection['heading'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php $renderBlocks($closingSection['blocks'] ?? [], is_array($closingSection['content'] ?? null) ? $closingSection['content'] : null); ?>
                        </section>
                    <?php endif; ?>

                    <?php require __DIR__ . '/Partials/render_reference_list.php'; ?>
                </article>
            </div>
        <?php endif; ?>
    </div>
</main>
