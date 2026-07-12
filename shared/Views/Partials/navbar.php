<?php

declare(strict_types=1);

/**
 * Sneat-style admin navbar for the authenticated app shell.
 * Keeps the existing shell hooks for sidebar toggle, notifications, and logout.
 */

$currentUserContext ??= null;

$userName = trim((string) ($_SESSION['username'] ?? $_SESSION['USERNAME'] ?? ''));
if ($userName === '' && $currentUserContext !== null) {
    $userName = 'User #' . $currentUserContext->userId;
}
$userName = $userName !== '' ? $userName : 'User';

$role = $currentUserContext !== null
    ? (string) ($currentUserContext->roleType ?? 'Authenticated')
    : 'Authenticated';
$role = ucwords(str_replace(['-', '_'], ' ', trim($role)));

$parts = preg_split('/\s+/', $userName) ?: [];
$initials = '';
foreach ($parts as $part) {
    if ($part !== '') {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    if (strlen($initials) >= 2) {
        break;
    }
}
$initials = $initials !== '' ? $initials : 'U';

$shortcuts = [
    ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'bi-grid-1x2', 'desc' => 'Home'],
    ['label' => 'Assessments', 'url' => '/assessments', 'icon' => 'bi-clipboard-pulse', 'desc' => 'Review work'],
    ['label' => 'Tasks', 'url' => '/tasks', 'icon' => 'bi-list-task', 'desc' => 'Track action'],
    ['label' => 'Corrective', 'url' => '/corrective-actions', 'icon' => 'bi-check2-square', 'desc' => 'Fix risks'],
    ['label' => 'Reports', 'url' => '/reporting/dashboard', 'icon' => 'bi-bar-chart-line', 'desc' => 'See trends'],
    ['label' => 'Profile', 'url' => '/profile', 'icon' => 'bi-person', 'desc' => 'Account'],
];

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)" id="sidebarToggle" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end flex-grow-1" id="navbar-collapse">
        <div class="navbar-nav align-items-center flex-grow-1">
            <div class="nav-item mb-0">
                <button type="button" class="nav-item nav-link px-0 d-inline-flex align-items-center gap-2" aria-label="Search">
                    <span class="d-inline-flex align-items-center gap-2">
                        <i class="bi bi-search"></i>
                        <span class="d-inline-block text-body-secondary fw-normal">Search <span class="d-none d-xxl-inline">[CTRL + K]</span></span>
                    </span>
                </button>
            </div>
        </div>

        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
            <li class="nav-item d-none d-lg-flex align-items-center me-3">
                <div class="d-none" id="tenantSwitcherWrap">
                    <select id="tenantSwitcherSelect" class="form-select form-select-sm">
                        <option value="">Organization</option>
                    </select>
                </div>
            </li>
            <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Quick links">
                    <i class="bi bi-grid icon-base icon-md"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end p-0">
                    <div class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h6 class="mb-0 me-auto">Shortcuts</h6>
                        </div>
                    </div>
                    <div class="dropdown-shortcuts-list scrollable-container">
                        <?php foreach (array_chunk($shortcuts, 2) as $shortcutRow): ?>
                            <div class="row row-bordered overflow-visible g-0">
                                <?php foreach ($shortcutRow as $shortcut): ?>
                                    <div class="dropdown-shortcuts-item col">
                                        <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                            <i class="bi <?= $e($shortcut['icon']) ?> text-heading"></i>
                                        </span>
                                        <a href="<?= $e($shortcut['url']) ?>" class="stretched-link"><?= $e($shortcut['label']) ?></a>
                                        <small><?= $e($shortcut['desc']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </li>
            <li class="nav-item dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" id="nav-theme" href="javascript:void(0);" data-bs-toggle="dropdown" aria-label="Toggle theme">
                    <i class="bi bi-sun-fill icon-base icon-md theme-icon-active" id="nav-theme-icon"></i>
                    <span class="d-none ms-2" id="nav-theme-text">Toggle theme</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
                    <li>
                        <button type="button" class="dropdown-item align-items-center active" data-bs-theme-value="light" aria-pressed="true">
                            <span><i class="bi bi-sun-fill me-3" data-icon="sun"></i>Light</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="dark" aria-pressed="false">
                            <span><i class="bi bi-moon-fill me-3" data-icon="moon"></i>Dark</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="system" aria-pressed="false">
                            <span><i class="bi bi-display me-3" data-icon="desktop"></i>System</span>
                        </button>
                    </li>
                </ul>
            </li>
            <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2" id="notificationsDropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-auto-close="outside" aria-expanded="false" id="notifToggle">
                    <span class="position-relative">
                        <i class="bi bi-bell icon-base icon-md"></i>
                        <span class="badge rounded-pill bg-danger badge-dot badge-notifications border d-none" id="notifBadge"></span>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end p-0" id="notifMenu">
                    <div class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h6 class="mb-0 me-auto">Notification</h6>
                            <div class="d-flex align-items-center h6 mb-0">
                                <span class="badge bg-label-primary me-2">New</span>
                                <button type="button" class="dropdown-notifications-all p-2 btn btn-link" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Mark all as read" title="Mark all as read" id="notifMarkAllRead">
                                    <i class="bi bi-envelope-open text-heading"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-notifications-list scrollable-container notif-body" id="notifBody">
                        <div class="text-center py-4 text-muted" id="notifLoading">
                            <div class="spinner-border spinner-border-sm text-muted me-2" role="status"></div>
                            Loading...
                        </div>
                    </div>
                </div>
            </li>

            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false" id="userMenuToggle">
                    <div class="avatar avatar-primary avatar-online">
                        <span><?= $e($initials) ?></span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 pt-2 pb-1">
                        <p class="fw-semibold mb-0 small"><?= $e($userName) ?></p>
                        <p class="mb-1 text-capitalize text-muted text-xs" style="font-size: 0.75rem;"><?= $e($role) ?></p>
                    </li>
                    <li>
                        <hr class="dropdown-divider my-1">
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="/profile">
                            <i class="bi bi-person text-muted"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="/logout" data-app-logout>
                            <i class="bi bi-box-arrow-right text-muted"></i>
                            <span>Sign out</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
