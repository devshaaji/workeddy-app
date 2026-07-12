<?php

declare(strict_types=1);

/** @var ?\WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage $page */
/** @var array<string, mixed> $summary */
/** @var ?string $message */
/** @var bool $canEditPage */
/** @var bool $canViewHistory */

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
            'plainText' => $section->plainText,
        ];
    }
}

$emptyMessage = $message ?? 'This content page has not been published yet.';
$references = $page?->references ?? [];
$referenceMap = [];
foreach ($references as $reference) {
    $referenceMap[$reference->sectionKey ?? '_global'][] = $reference;
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

$renderBlocks = static function (array $blocks): void {
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? '');
        if ($type === 'paragraph') {
            echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
            continue;
        }
        if ($type === 'rich_text') {
            echo '<p>' . nl2br(htmlspecialchars((string) ($block['body'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
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

$renderReferences = static function (array $sectionReferences): void {
    if ($sectionReferences === []) {
        return;
    }

    echo '<div class="methodology-inline-note">';
    echo '<h3 class="small text-uppercase fw-semibold text-muted mb-3">Sources</h3>';
    echo '<div class="methodology-reference-list">';
    foreach ($sectionReferences as $reference) {
        $referenceMeta = trim(implode(' | ', array_filter([$reference->author, $reference->year])));
        echo '<article class="methodology-reference-entry">';
        echo '<div class="fw-semibold mb-1">' . htmlspecialchars($reference->title, ENT_QUOTES, 'UTF-8') . '</div>';
        if ($referenceMeta !== '') {
            echo '<div class="methodology-reference-meta small text-muted">' . htmlspecialchars($referenceMeta, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($reference->citation !== null && $reference->citation !== '') {
            echo '<p>' . htmlspecialchars($reference->citation, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($reference->url !== null && $reference->url !== '') {
            echo '<a href="' . htmlspecialchars($reference->url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">Open source</a>';
        }
        echo '</article>';
    }
    echo '</div>';
    echo '</div>';
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
                            <?php $renderBlocks($introSection['blocks'] ?? []); ?>
                        </section>
                    <?php endif; ?>

                    <?php foreach ($middleSections as $section): ?>
                        <?php
                        $sectionKey = (string) ($section['sectionKey'] ?? '');
                        $anchor = 'methodology-' . preg_replace('/[^a-z0-9-]+/i', '-', $sectionKey);
                        $sectionReferences = $referenceMap[$sectionKey] ?? [];
                        ?>
                        <section id="<?= htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') ?>" class="methodology-tree-section">
                            <h2 class="h5 fw-semibold mb-3"><?= htmlspecialchars((string) ($section['heading'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php $renderBlocks($section['blocks'] ?? []); ?>
                            <?php $renderReferences($sectionReferences); ?>
                        </section>
                    <?php endforeach; ?>

                    <?php if ($closingSection !== null && $closingSection !== $introSection): ?>
                        <?php
                        $closingKey = (string) ($closingSection['sectionKey'] ?? '');
                        $closingAnchor = 'methodology-' . preg_replace('/[^a-z0-9-]+/i', '-', $closingKey);
                        $closingReferences = $referenceMap[$closingKey] ?? [];
                        ?>
                        <section id="<?= htmlspecialchars($closingAnchor, ENT_QUOTES, 'UTF-8') ?>" class="methodology-tree-closing">
                            <h2 class="h4 fw-semibold mb-3"><?= htmlspecialchars((string) ($closingSection['heading'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php $renderBlocks($closingSection['blocks'] ?? []); ?>
                            <?php $renderReferences($closingReferences); ?>
                        </section>
                    <?php endif; ?>

                    <?php if (($referenceMap['_global'] ?? []) !== []): ?>
                        <section class="methodology-tree-section">
                            <h2 class="h5 fw-semibold mb-3">Additional sources</h2>
                            <?php $renderReferences($referenceMap['_global']); ?>
                        </section>
                    <?php endif; ?>
                </article>
            </div>
        <?php endif; ?>
    </div>
</main>