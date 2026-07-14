<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $contentSections */
/** @var string $emptyMessage */
/** @var array<string, int> $referenceIndexByKey */
/** @var array<string, int> $referenceIndexByTitle */

use WorkEddy\Modules\Content\Support\ContentRichTextRenderer;

$emptyMessage = $emptyMessage ?? 'No content is available yet.';
$referenceIndexByKey = is_array($referenceIndexByKey ?? null) ? $referenceIndexByKey : [];
$referenceIndexByTitle = is_array($referenceIndexByTitle ?? null) ? $referenceIndexByTitle : [];
$renderRichText = static function (string $value): string {
    return $value;
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
                <?php if (is_array($section['content'] ?? null) && (($section['content']['format'] ?? null) === 'quill_delta')): ?>
                    <div class="content-richtext-body">
                        <?= ContentRichTextRenderer::render($section['content'], $referenceIndexByKey, $referenceIndexByTitle) ?>
                    </div>
                    <?php continue; ?>
                <?php endif; ?>
                <?php foreach (($section['blocks'] ?? []) as $block): ?>
                    <?php $type = (string) ($block['type'] ?? ''); ?>
                    <?php if ($type === 'paragraph'): ?>
                        <p class="mb-3"><?= htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif ($type === 'rich_text'): ?>
                        <div class="mb-3 text-body-secondary content-richtext-body"><?= $renderRichText((string) ($block['body'] ?? '')) ?></div>
                    <?php elseif ($type === 'list'): ?>
                        <ul class="mb-3">
                            <?php foreach (($block['items'] ?? []) as $item): ?>
                                <li><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($type === 'image'): ?>
                        <div class="content-image-box">
                            <figure class="mb-0">
                                <?php
                                $storageFileUuid = trim((string) ($block['storageFileUuid'] ?? ''));
                                $previewUrl = trim((string) ($block['previewUrl'] ?? ''));
                                $imageSrc = $previewUrl !== '' ? $previewUrl : ($storageFileUuid !== '' ? '/api/v1/storage/files/' . rawurlencode($storageFileUuid) . '/view' : '');
                                ?>
                                <?php if ($imageSrc !== ''): ?>
                                    <img src="<?= htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($block['altText'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="content-image-embed__image" loading="lazy">
                                <?php else: ?>
                                    <div class="border rounded p-3 bg-body text-muted small">
                                        <div class="fw-semibold mb-1">Image preview unavailable</div>
                                        <div>Media UUID: <?= htmlspecialchars((string) ($block['mediaUuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($block['caption'])): ?>
                                    <figcaption class="content-image-embed__caption small text-muted mt-2 mb-0">
                                        <div class="content-image-embed__title"><?= htmlspecialchars((string) $block['caption'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($block['altText'])): ?>
                                            <div class="content-image-embed__meta"><?= htmlspecialchars((string) $block['altText'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </figcaption>
                                <?php endif; ?>
                            </figure>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
