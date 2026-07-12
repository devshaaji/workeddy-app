<?php

declare(strict_types=1);

/**
 * Standardized Page Header Component (Sneat / Bootstrap 5.3)
 *
 * Provides a consistent page heading area with breadcrumbs, page title,
 * and page-level actions.
 *
 * Design rule:
 * - Breadcrumbs provide page context.
 * - $pageTitle identifies the current page.
 * - $pagePurpose is optional and should only be used when extra explanation
 *   is genuinely needed.
 *
 * Usage rules:
 * - Always provide $pageTitle.
 * - Use $breadcrumbs when the page belongs inside a module or section.
 * - Avoid $pagePurpose when breadcrumbs and page content already explain the page.
 * - Use $pagePurpose only for unusual pages, onboarding screens, empty states,
 *   setup flows, or pages with unclear business meaning.
 * - Use $pageActions for page-level actions only, not row-level actions.
 *
 * Action behavior:
 * - Desktop: show a maximum of two visible action buttons.
 * - Desktop: move any additional actions into a three-vertical-dots dropdown.
 * - Buttons may include optional icons.
 * - Mobile: show only the primary action button as icon-only.
 * - Mobile: move all remaining actions into the three-vertical-dots dropdown beside it.
 *
 * Variables:
 *   $pageTitle    string       Required. Main page title.
 *   $pagePurpose  string|null  Optional. Extra helper text, rarely needed.
 *   $pageActions  array        Optional. Action buttons:
 *                              [
 *                                  [
 *                                      'label'   => string,
 *                                      'url'     => string|null,
 *                                      'class'   => string|null,
 *                                      'icon'    => string|null,
 *                                      'onclick' => string|null,
 *                                      'id'      => string|null,
 *                                  ]
 *                              ]
 *   $breadcrumbs  array        Optional. Breadcrumb segments:
 *                              [
 *                                  [
 *                                      'label' => string,
 *                                      'url'   => string|null,
 *                                  ]
 *                              ]
 */

$pageTitle = htmlspecialchars((string) ($pageTitle ?? 'Untitled'), ENT_QUOTES, 'UTF-8');
$pagePurpose = htmlspecialchars((string) ($pagePurpose ?? ''), ENT_QUOTES, 'UTF-8');
$pageActions = is_array($pageActions ?? null) ? $pageActions : [];
$breadcrumbs = is_array($breadcrumbs ?? null) ? $breadcrumbs : [];

// Parse actions
$primaryAction = $pageActions[0] ?? null;
$secondaryAction = $pageActions[1] ?? null;
$extraActions = array_slice($pageActions, 2);

$totalActions = count($pageActions);

// Helper function to render a single button action
$renderButtonAction = function (array $action, string $extraClass = '', bool $isPrimary = false): string {
    $label = htmlspecialchars((string) ($action['label'] ?? 'Action'), ENT_QUOTES, 'UTF-8');
    $href = htmlspecialchars((string) ($action['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
    $class = htmlspecialchars((string) ($action['class'] ?? 'btn btn-primary'), ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars((string) ($action['icon'] ?? ''), ENT_QUOTES, 'UTF-8');
    $onclick = isset($action['onclick']) ? ' onclick="' . htmlspecialchars((string) $action['onclick'], ENT_QUOTES, 'UTF-8') . '"' : '';
    $id = isset($action['id']) ? ' id="' . htmlspecialchars((string) $action['id'], ENT_QUOTES, 'UTF-8') . '"' : '';
    
    // Add extra responsive classes
    if ($extraClass !== '') {
        $class .= ' ' . $extraClass;
    }
    
    $iconHtml = '';
    if ($icon !== '') {
        $iconHtml = '<i class="bi bi-' . $icon . ($isPrimary ? ' me-sm-1' : ' me-1') . '"></i>';
    } else if ($isPrimary) {
        // Fallback icon for mobile if primary has no icon
        $iconHtml = '<i class="bi bi-lightning-fill me-sm-1"></i>';
    }
    
    // Wrap label in a responsive span for primary action on mobile
    $labelHtml = $label;
    if ($isPrimary) {
        $labelHtml = '<span class="d-none d-sm-inline">' . $label . '</span>';
    }
    
    return '<a href="' . $href . '" class="' . $class . '"' . $onclick . $id . '>' . $iconHtml . $labelHtml . '</a>';
};

// Helper function to render a dropdown item action
$renderDropdownAction = function (array $action, string $extraClass = ''): string {
    $label = htmlspecialchars((string) ($action['label'] ?? 'Action'), ENT_QUOTES, 'UTF-8');
    $href = htmlspecialchars((string) ($action['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
    $class = 'dropdown-item';
    if ($extraClass !== '') {
        $class .= ' ' . $extraClass;
    }
    $icon = htmlspecialchars((string) ($action['icon'] ?? ''), ENT_QUOTES, 'UTF-8');
    $onclick = isset($action['onclick']) ? ' onclick="' . htmlspecialchars((string) $action['onclick'], ENT_QUOTES, 'UTF-8') . '"' : '';
    $id = isset($action['id']) ? ' id="' . htmlspecialchars((string) $action['id'], ENT_QUOTES, 'UTF-8') . '"' : '';
    
    $iconHtml = '';
    if ($icon !== '') {
        $iconHtml = '<i class="bi bi-' . $icon . ' me-2 text-muted"></i>';
    }
    
    return '<li><a href="' . $href . '" class="' . $class . '"' . $onclick . $id . '>' . $iconHtml . $label . '</a></li>';
};
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
    <!-- Title and Breadcrumb -->
    <div>
        <?php if ($breadcrumbs !== []): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-sm">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i === count($breadcrumbs) - 1): ?>
                            <li class="breadcrumb-item active text-muted" aria-current="page">
                                <?= htmlspecialchars((string) $crumb['label'], ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item">
                                <a href="<?= htmlspecialchars((string) $crumb['url'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">
                                    <?= htmlspecialchars((string) $crumb['label'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php elseif ($pagePurpose !== ''): ?>
            <span class="text-muted text-uppercase text-xs fw-semibold tracking-wider d-block mb-1">
                <?= $pagePurpose ?>
            </span>
        <?php endif; ?>

        <h4 class="fw-bold mb-0" style="color: var(--we-heading)">
            <?= $pageTitle ?>
        </h4>
    </div>

    <!-- Page Actions -->
    <?php if ($totalActions > 0): ?>
        <div class="d-flex align-items-center gap-2">
            <!-- 1. Primary Action (Always visible, icon only on mobile) -->
            <?= $renderButtonAction($primaryAction, '', true) ?>

            <!-- 2. Secondary Action (Visible only on desktop/tablet, hidden on mobile) -->
            <?php if ($secondaryAction !== null): ?>
                <?= $renderButtonAction($secondaryAction, 'd-none d-sm-inline-block') ?>
            <?php endif; ?>

            <!-- 3. Actions Dropdown Menu -->
            <?php 
            // The dropdown trigger is visible on mobile if totalActions >= 2.
            // On desktop/tablet, it is only visible if totalActions >= 3.
            $dropdownTriggerClass = 'btn btn-outline-secondary btn-icon';
            if ($totalActions === 2) {
                $dropdownTriggerClass .= ' d-sm-none'; // Hide on desktop/tablet
            }
            ?>
            <?php if ($totalActions >= 2): ?>
                <div class="dropdown d-inline-block">
                    <button class="<?= $dropdownTriggerClass ?>" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <!-- On mobile, the secondary action goes here -->
                        <?php if ($secondaryAction !== null): ?>
                            <?= $renderDropdownAction($secondaryAction, 'd-sm-none') ?>
                        <?php endif; ?>

                        <!-- Any additional actions always go here -->
                        <?php foreach ($extraActions as $action): ?>
                            <?= $renderDropdownAction($action) ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
