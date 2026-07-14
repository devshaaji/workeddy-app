<?php
declare(strict_types=1);

/** @var list<object|array<string, mixed>> $references */

$references = $references ?? [];
?>
<?php if ($references !== []): ?>
    <section class="content-references mt-5" id="content-references">
        <h2 class="h4 border-bottom pb-2 mb-3">References</h2>
        <ol class="content-references-list">
            <?php foreach ($references as $index => $reference): ?>
                <?php
                $number = $index + 1;
                $title = is_object($reference) ? (string) ($reference->title ?? '') : (string) ($reference['title'] ?? '');
                $author = is_object($reference) ? (string) ($reference->author ?? '') : (string) ($reference['author'] ?? '');
                $year = is_object($reference) ? (string) ($reference->year ?? '') : (string) ($reference['year'] ?? '');
                $citation = is_object($reference) ? (string) ($reference->citation ?? '') : (string) ($reference['citation'] ?? '');
                $url = is_object($reference) ? (string) ($reference->url ?? '') : (string) ($reference['url'] ?? '');
                $meta = trim(implode(' | ', array_filter([$author, $year], static fn(string $value): bool => $value !== '')));
                ?>
                <li class="content-references-item" id="content-reference-<?= $number ?>">
                    <div class="content-references-item__body">
                        <div class="content-references-item__title">
                            <?php if ($url !== ''): ?>
                                <a class="content-references-item__title-link" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                                    <?= htmlspecialchars($title !== '' ? $title : 'Untitled source', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($title !== '' ? $title : 'Untitled source', ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($meta !== ''): ?>
                            <div class="content-references-item__meta"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($citation !== ''): ?>
                            <p class="content-references-item__citation"><?= htmlspecialchars($citation, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                    <a class="content-references-item__backref" href="#content-reference-inline-<?= $number ?>" aria-label="Back to reference <?= $number ?>">&uarr;</a>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>
