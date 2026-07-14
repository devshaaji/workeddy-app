# Content Module Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the Content module's article page and preview layout to match the structure of the Claude Help Center, featuring a two-column desktop grid with a sticky Scroll-Spy Table of Contents on the right and clean inline typography instead of card elements.

**Architecture:** 
1. Create a module CSS file for layout grid/typography.
2. Create a module JS file for Table of Contents scroll-spy active state handling.
3. Update `page.php` and `preview.php` to define the assets and structure.
4. Refactor `render_sections.php` to use spacious inline section layouts instead of boxy cards, ensuring the `.content-section-card` class persists for test compatibility.

**Tech Stack:** PHP 8.1, Bootstrap 5.3, Vanilla CSS, Vanilla JavaScript.

## Global Constraints
- Do not remove class names or references tested in PHPUnit without adjusting them to pass. Specifically, keep `content-section-card` in `render_sections.php` to pass `ContentReadPagesPresentationTest`.
- Ensure strict declaration: `declare(strict_types=1);` is used in php files.

---

### Task 1: Create Module Styling File

**Files:**
- Create: `public/assets/css/modules/content-page.css`

**Interfaces:**
- Produces: CSS classes `.content-article-grid`, `.content-article-metadata`, `.content-article-sidebar`, `.content-toc`, `.content-toc-list`, `.content-toc-link`, `.content-article-main`, and `.content-image-box`.

- [ ] **Step 1: Write CSS rules for layout, typography, and Table of Contents**

Create file `c:\xampp\htdocs\workeddy-app\public\assets\css\modules\content-page.css`:
```css
/* Container and layout grid */
.content-article-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 2.5rem;
    align-items: start;
    margin-top: 1rem;
}

@media (min-width: 1100px) {
    .content-article-grid {
        grid-template-columns: minmax(0, 760px) 260px;
        justify-content: center;
    }
}

/* Metadata area */
.content-article-metadata {
    border-bottom: 1px solid var(--bs-border-color);
    padding-bottom: 1rem;
    margin-bottom: 2rem;
}

/* Sidebar and Table of Contents */
.content-article-sidebar {
    position: sticky;
    top: 8rem;
}

@media (max-width: 1099.98px) {
    .content-article-sidebar {
        display: none;
    }
}

.content-toc-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    font-weight: 600;
    color: var(--bs-secondary-color);
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
}

.content-toc-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-left: 1px solid var(--bs-border-color);
    padding-left: 1rem;
    list-style: none;
    margin: 0;
}

.content-toc-link {
    display: block;
    font-size: 0.875rem;
    color: var(--bs-secondary-color);
    text-decoration: none;
    line-height: 1.4;
    transition: color 0.15s ease-in-out, border-color 0.15s ease-in-out;
    border-left: 2px solid transparent;
    margin-left: calc(-1rem - 1px);
    padding-left: calc(1rem - 1px);
}

.content-toc-link:hover {
    color: var(--bs-emphasis-color);
}

.content-toc-link.is-active {
    color: var(--bs-primary);
    font-weight: 600;
    border-left-color: var(--bs-primary);
}

/* Typography styles for the main article content */
.content-article-main {
    max-width: 760px;
    width: 100%;
}

.content-article-main h2 {
    font-family: Georgia, serif;
    font-weight: 700;
    font-size: 1.5rem;
    color: var(--bs-emphasis-color);
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    scroll-margin-top: 6.5rem;
}

.content-article-main p {
    font-size: 1rem;
    line-height: 1.75;
    color: var(--bs-body-color);
    margin-bottom: 1.25rem;
}

.content-article-main ul {
    margin-bottom: 1.25rem;
    padding-left: 1.5rem;
}

.content-article-main li {
    font-size: 1rem;
    line-height: 1.75;
    color: var(--bs-body-color);
    margin-bottom: 0.5rem;
}

.content-image-box {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
```

---

### Task 2: Create Module Script File

**Files:**
- Create: `public/assets/js/modules/content-page.js`

**Interfaces:**
- Consumes: `.content-article-grid`, `.content-toc-link`, sections by id.
- Produces: Dynamic active class toggle for TOC link elements as sections are scrolled.

- [ ] **Step 1: Write Scroll-Spy active highlighting script**

Create file `c:\xampp\htdocs\workeddy-app\public\assets\js\modules\content-page.js`:
```javascript
(function () {
    'use strict';

    var container = document.querySelector('.content-article-grid');
    if (!container) {
        return;
    }

    var links = Array.prototype.slice.call(container.querySelectorAll('.content-toc-link'));
    var sections = links
        .map(function (link) {
            var id = (link.getAttribute('href') || '').replace('#', '');
            var target = id ? document.getElementById(id) : null;
            return target ? { link: link, target: target } : null;
        })
        .filter(Boolean);

    if (sections.length === 0) {
        return;
    }

    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var activeIndex = -1;
    var ticking = false;

    // Smooth-scroll when clicking TOC link
    links.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var id = (link.getAttribute('href') || '').replace('#', '');
            var target = id ? document.getElementById(id) : null;
            if (!target) {
                return;
            }
            event.preventDefault();
            target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            history.replaceState(null, '', '#' + id);
        });
    });

    var setActive = function (activeIndex) {
        if (activeIndex === -1) {
            return;
        }
        sections.forEach(function (entry, index) {
            entry.link.classList.toggle('is-active', index === activeIndex);
        });
    };

    var getActiveIndex = function () {
        var scrollPosition = window.pageYOffset || document.documentElement.scrollTop || 0;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        var trackingLine = scrollPosition + Math.max(180, viewportHeight * 0.3);
        var nextIndex = 0;

        sections.forEach(function (entry, index) {
            var rect = entry.target.getBoundingClientRect();
            var top = rect.top + scrollPosition;
            var bottom = rect.bottom + scrollPosition;
            if (top <= trackingLine && bottom > trackingLine) {
                nextIndex = index;
            } else if (top <= trackingLine) {
                nextIndex = index;
            }
        });

        return nextIndex;
    };

    var syncActiveState = function () {
        ticking = false;
        var nextIndex = getActiveIndex();
        if (nextIndex !== activeIndex) {
            activeIndex = nextIndex;
            setActive(activeIndex);
        }
    };

    var requestSync = function () {
        if (ticking) {
            return;
        }
        ticking = true;
        window.requestAnimationFrame(syncActiveState);
    };

    window.addEventListener('scroll', requestSync, { passive: true });
    window.addEventListener('resize', requestSync);
    requestSync();

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(requestSync, {
            rootMargin: '0px',
            threshold: [0, 0.25, 0.5, 0.75, 1]
        });

        sections.forEach(function (entry) {
            observer.observe(entry.target);
        });
    }
})();
```

---

### Task 3: Restructure Views to Use New Columns & TOC

**Files:**
- Modify: `modules/Content/Presentation/Views/Partials/render_sections.php`
- Modify: `modules/Content/Presentation/Views/page.php`
- Modify: `modules/Content/Presentation/Views/preview.php`

- [ ] **Step 1: Modify `render_sections.php` to clean up cards and render clean text blocks**

Overwrite `c:\xampp\htdocs\workeddy-app\modules\Content\Presentation\Views\Partials\render_sections.php`:
```php
<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $contentSections */
/** @var string $emptyMessage */

$emptyMessage = $emptyMessage ?? 'No content is available yet.';
$renderRichText = static function (string $value): string {
    return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
};
?>

<?php if ($contentSections === []): ?>
    <div class="border rounded p-4 text-muted text-center bg-light">
        <?= htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php else: ?>
    <div class="content-sections-wrapper">
        <?php foreach ($contentSections as $section): ?>
            <?php 
                $heading = (string) ($section['heading'] ?? '');
                $sectionId = 'section-' . preg_replace('/[^a-z0-9-]+/i', '-', strtolower($heading));
            ?>
            <section id="<?= htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8') ?>" class="content-section-card mb-5">
                <h2 class="h4 border-bottom pb-2 mb-3"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
                <?php foreach (($section['blocks'] ?? []) as $block): ?>
                    <?php $type = (string) ($block['type'] ?? ''); ?>
                    <?php if ($type === 'paragraph'): ?>
                        <p class="mb-3"><?= htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif ($type === 'rich_text'): ?>
                        <div class="mb-3 text-body-secondary"><?= $renderRichText((string) ($block['body'] ?? '')) ?></div>
                    <?php elseif ($type === 'list'): ?>
                        <ul class="mb-3">
                            <?php foreach (($block['items'] ?? []) as $item): ?>
                                <li><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($type === 'image'): ?>
                        <div class="content-image-box">
                            <figure class="mb-0">
                                <div class="border rounded p-3 bg-body text-muted small">
                                    <div class="fw-semibold mb-1">Image block</div>
                                    <div>Media UUID: <?= htmlspecialchars((string) ($block['mediaUuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($block['altText'])): ?>
                                        <div>Alt text: <?= htmlspecialchars((string) $block['altText'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($block['caption'])): ?>
                                    <figcaption class="small text-muted mt-2 mb-0"><?= htmlspecialchars((string) $block['caption'], ENT_QUOTES, 'UTF-8') ?></figcaption>
                                <?php endif; ?>
                            </figure>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
```

- [ ] **Step 2: Modify `page.php` to define css/js, build layout grid and metadata header**

Overwrite `c:\xampp\htdocs\workeddy-app\modules\Content\Presentation\Views\page.php`:
```php
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
```

- [ ] **Step 3: Modify `preview.php` to match `page.php` exactly for visual parity**

Overwrite `c:\xampp\htdocs\workeddy-app\modules\Content\Presentation\Views\preview.php`:
```php
<?php

declare(strict_types=1);

/** @var array<string, mixed> $summary */
/** @var ?\WorkEddy\Modules\Content\Application\DTOs\ContentPreviewPage $preview */

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
```

---

### Task 4: Run Tests & Verification

**Files:**
- Test: `tests/Content/ContentReadPagesPresentationTest.php`

- [ ] **Step 1: Execute foundation test suite**

Run: `composer test` or `php tests/run.php`
Expected: ALL foundation test suites (specifically `ContentReadPagesPresentationTest`) pass successfully.
