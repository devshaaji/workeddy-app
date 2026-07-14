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
