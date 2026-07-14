<?php
$v2Root = dirname(__DIR__, 5);
$settingsModules = $settingsModules ?? [];
$activeModule = $activeSettingsModule ?? null;
$settings = $settings ?? [];
$settingDefinitions = $settingDefinitions ?? [];
$pageTitle = ($activeModule['label'] ?? 'Platform') . ' Settings';
$pagePurpose = 'Platform';
$pageCss = ['css/modules/settings-page.css'];
$pageScripts = ['js/modules/settings-page.js'];
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$moduleKey = (string) ($activeModule['key'] ?? '');
$canEdit = !empty($activeModule['canEdit']);
$sectionId = static function (string $label): string {
    $slug = strtolower(trim($label));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '');
    $slug = trim((string) $slug, '-');

    return $slug !== '' ? 'settings-section-' . $slug : 'settings-section-general';
};

$formatJson = static function (mixed $value): string {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    if (!is_array($value)) {
        return (string) $value;
    }

    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
};

$fieldValue = static function (object $definition, array $settings) use ($formatJson): string {
    $value = $settings[$definition->key] ?? $definition->default;

    return match ($definition->type->value) {
        'boolean' => $value ? '1' : '0',
        'json' => $formatJson($value),
        default => (string) $value,
    };
};

$tooltipText = static function (object $definition): string {
    $parts = [];
    $description = trim((string) ($definition->description ?? ''));
    if ($description !== '') {
        $parts[] = $description;
    }
    if (!empty($definition->restartRequired)) {
        $parts[] = 'Restart required after changes.';
    }
    if (empty($definition->editable)) {
        $parts[] = 'This setting is read-only.';
    }

    return implode(' ', $parts);
};

$groupedDefinitions = [];
foreach ($settingDefinitions as $definition) {
    $section = trim((string) ($definition->section ?? ''));
    if ($section === '') {
        $section = 'General';
    }

    $groupedDefinitions[$section][] = $definition;
}

$activeSectionEntries = [];
foreach ($groupedDefinitions as $sectionLabel => $sectionDefinitions) {
    $activeSectionEntries[] = [
        'id' => $sectionId($sectionLabel),
        'label' => $sectionLabel,
    ];
}

require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row g-4" data-settings-screen="module-settings">
    <div class="col-xl-3 col-lg-4 d-none d-lg-block">
        <div class="settings-nav-sticky">
            <div class="card bg-transparent border-0 settings-nav-card">
                <div class="card-header pb-2">
                    <span class="badge bg-label-primary mb-2">Settings Navigator</span>
                </div>
                <div class="card-body pt-2">
                    <ul class="timeline settings-nav-timeline mb-0 py-3">
                        <?php foreach ($settingsModules as $index => $module): ?>
                            <?php
                            $isActiveModule = ($module['key'] ?? '') === $moduleKey;
                            $moduleUrl = (string) ($module['url'] ?? '#');
                            ?>
                            <li class="timeline-item timeline-item-transparent<?= $isActiveModule ? ' settings-nav-item-active' : ' border-dashed' ?>">
                                <span class="timeline-point <?= $isActiveModule ? 'timeline-point-primary' : 'timeline-point-secondary' ?>"></span>
                                <div class="timeline-event">
                                    <div class="timeline-header align-items-start">
                                        <div class="settings-nav-module">
                                            <a href="<?= $e($moduleUrl) ?>" class="settings-nav-link <?= $isActiveModule ? 'fw-bold ' : '' ?> <?= $isActiveModule ? 'active' : '' ?>">
                                                <?= $e($module['label'] ?? '') ?>
                                            </a>
                                        </div>
                                    </div>

                                    <?php if ($isActiveModule && $activeSectionEntries !== []): ?>
                                        <div class="settings-nav-sections">
                                            <ul class="timeline settings-nav-subtimeline mb-0">
                                                <?php foreach ($activeSectionEntries as $sectionIndex => $section): ?>
                                                    <li class="timeline-item timeline-item-transparent border-dashed">
                                                        <span class="timeline-point timeline-point-primary"></span>
                                                        <div class="timeline-event">
                                                            <a class="settings-nav-section-link" href="#<?= $e($section['id']) ?>">
                                                                <span><?= $e($section['label']) ?></span>
                                                            </a>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-9 col-lg-8">
        <form
            id="platform-settings-form"
            method="post"
            action="/api/v1/settings/<?= $e($moduleKey) ?>"
            data-settings-module="<?= $e($moduleKey) ?>">
            <div class="card mb-4">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1"><?= $e($activeModule['label'] ?? 'Module') ?> Settings</h3>
                        <div class="text-secondary">Registry-backed configuration for this module.</div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($settingDefinitions === []): ?>
                        <div class="text-muted">No settings are registered for this module.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-4">
                            <?php foreach ($groupedDefinitions as $sectionLabel => $sectionDefinitions): ?>
                                <section id="<?= $e($sectionId($sectionLabel)) ?>" class="border rounded-3 p-3 p-lg-4">
                                    <div class="mb-3">
                                        <h4 class="h6 mb-1"><?= $e($sectionLabel) ?></h4>
                                        <div class="text-secondary small"><?= count($sectionDefinitions) ?> setting<?= count($sectionDefinitions) === 1 ? '' : 's' ?></div>
                                    </div>

                                    <div class="row g-4">
                                        <?php foreach ($sectionDefinitions as $definition): ?>
                                            <?php
                                            $value = $fieldValue($definition, $settings);
                                            $helpText = $tooltipText($definition);
                                            $isBool = $definition->type->value === 'boolean';
                                            $isJson = $definition->type->value === 'json';
                                            $isNumber = in_array($definition->type->value, ['integer', 'float'], true);
                                            $isLongText = !$isJson && $definition->type->value === 'string' && (strlen((string) $value) > 120 || str_contains((string) $value, "\n"));
                                            ?>
                                            <div class="col-12<?= $isBool ? '' : ($isJson || $isLongText ? '' : ' col-md-6') ?>">
                                                <?php if ($isBool): ?>
                                                    <div class="form-check form-switch">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            id="setting-<?= $e($definition->key) ?>"
                                                            name="<?= $e($definition->key) ?>"
                                                            value="1"
                                                            data-setting-type="boolean"
                                                            data-default="<?= $definition->default ? '1' : '0' ?>"
                                                            <?= !empty($settings[$definition->key]) ? 'checked' : '' ?>
                                                            <?= !$definition->editable || !$canEdit ? 'disabled' : '' ?>>
                                                        <label class="form-check-label d-inline-flex align-items-center gap-2" for="setting-<?= $e($definition->key) ?>">
                                                            <span><?= $e($definition->label ?: $definition->key) ?></span>
                                                            <?php if ($helpText !== ''): ?>
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-icon p-0 border-0 bg-transparent text-muted"
                                                                    data-bs-toggle="tooltip"
                                                                    data-bs-placement="top"
                                                                    data-bs-title="<?= $e($helpText) ?>"
                                                                    aria-label="Help for <?= $e($definition->label ?: $definition->key) ?>">
                                                                    <i class="bi bi-info-circle"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                <?php elseif ($isJson || $isLongText): ?>
                                                    <label class="form-label d-inline-flex align-items-center gap-2" for="setting-<?= $e($definition->key) ?>">
                                                        <span><?= $e($definition->label ?: $definition->key) ?></span>
                                                        <?php if ($helpText !== ''): ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-icon p-0 border-0 bg-transparent text-muted"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                                data-bs-title="<?= $e($helpText) ?>"
                                                                aria-label="Help for <?= $e($definition->label ?: $definition->key) ?>">
                                                                <i class="bi bi-info-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </label>
                                                    <textarea
                                                        id="setting-<?= $e($definition->key) ?>"
                                                        name="<?= $e($definition->key) ?>"
                                                        class="form-control"
                                                        rows="<?= $isJson ? '8' : '4' ?>"
                                                        data-setting-type="<?= $e($definition->type->value) ?>"
                                                        data-default="<?= $e($isJson ? $formatJson($definition->default) : (string) $definition->default) ?>"
                                                        <?= !$definition->editable || !$canEdit ? 'disabled' : '' ?>><?= $e($value) ?></textarea>
                                                <?php else: ?>
                                                    <label class="form-label d-inline-flex align-items-center gap-2" for="setting-<?= $e($definition->key) ?>">
                                                        <span><?= $e($definition->label ?: $definition->key) ?></span>
                                                        <?php if ($helpText !== ''): ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-icon p-0 border-0 bg-transparent text-muted"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                                data-bs-title="<?= $e($helpText) ?>"
                                                                aria-label="Help for <?= $e($definition->label ?: $definition->key) ?>">
                                                                <i class="bi bi-info-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </label>
                                                    <input
                                                        type="<?= $isNumber ? 'number' : 'text' ?>"
                                                        id="setting-<?= $e($definition->key) ?>"
                                                        name="<?= $e($definition->key) ?>"
                                                        class="form-control"
                                                        value="<?= $e($value) ?>"
                                                        data-setting-type="<?= $e($definition->type->value) ?>"
                                                        data-default="<?= $e((string) $definition->default) ?>"
                                                        <?= $definition->type->value === 'float' ? 'step="any"' : '' ?>
                                                        <?= !$definition->editable || !$canEdit ? 'disabled' : '' ?>>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="platform-settings-feedback" class="d-none mb-4" data-form-feedback></div>

            <?php if ($canEdit && $settingDefinitions !== []): ?>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="platform-settings-reset">Reset Defaults</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>