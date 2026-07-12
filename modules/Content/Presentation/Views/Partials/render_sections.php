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
    <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-body text-muted">
            <?= htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-4 contentManagedPage">
        <?php foreach ($contentSections as $section): ?>
            <article class="card content-section-card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1"><?= htmlspecialchars((string) ($section['heading'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h5>
                        <?php if (!empty($section['sectionKey'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars((string) $section['sectionKey'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>

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
                            <figure class="mb-3">
                                <div class="border rounded p-3 bg-light text-muted small">
                                    <div class="fw-semibold mb-1">Image block</div>
                                    <div>Media UUID: <?= htmlspecialchars((string) ($block['mediaUuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($block['altText'])): ?>
                                        <div>Alt text: <?= htmlspecialchars((string) $block['altText'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($block['caption'])): ?>
                                    <figcaption class="small text-muted mt-2"><?= htmlspecialchars((string) $block['caption'], ENT_QUOTES, 'UTF-8') ?></figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
