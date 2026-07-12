<?php

declare(strict_types=1);

/**
 * WorkEddy v2 – Sneat/Bootstrap authenticated app shell.
 *
 * All vendor JS/CSS is served from /assets/vendor/ (no CDN).
 * Runtime behaviour is in shell.js — this file is pure markup.
 *
 * Variables passed by ViewRenderer:
 *   $content            string – rendered page HTML
 *   $layoutTitle        string – browser tab title
 *   $currentView        string – view path for active-state detection
 *   $currentUserContext ?UserContext – authenticated user context
 *   $showSidebar        bool
 *   $pageTitle          string – page heading
 *   $pageScripts        array  – extra JS bundles
 */

$layoutTitle ??= 'WorkEddy';
$content ??= '';
$showSidebar ??= true;
$currentView ??= '';
$pageCss ??= [];
$pageScripts ??= [];
$currentUserContext ??= null;
$htmlClasses = [
    'layout-navbar-fixed',
    'layout-menu-fixed',
    'layout-compact',
];
if (!$showSidebar) {
    $htmlClasses[] = 'layout-without-menu';
}

$assetBase = '/assets';
$asset = static fn(string $path): string => htmlspecialchars($assetBase . '/' . ltrim($path, '/'), ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars((string) $layoutTitle, ENT_QUOTES, 'UTF-8');

// CSRF Token Management
if (session_status() === PHP_SESSION_NONE) {
    if (PHP_SAPI === 'cli' && headers_sent()) {
        $_SESSION ??= [];
    } else {
        session_start();
    }
}
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['_csrf_token'];

// ── Resolve user context safely ──────────────────────────────────────────
// UserContext->organizationUuid is nullable. We fall back through:
//   1. $currentUserContext->organizationUuid  (canonical)
//   2. $_SESSION['organization_uuid']         (legacy session key)
//   3. empty string                           (safe default — sidebar links show '#')
$organizationUuid = '';
if ($currentUserContext !== null && !empty($currentUserContext->organizationUuid)) {
    $organizationUuid = (string) $currentUserContext->organizationUuid;
} elseif (!empty($_SESSION['organization_uuid'])) {
    $organizationUuid = (string) $_SESSION['organization_uuid'];
}

$userId = $currentUserContext !== null ? $currentUserContext->userId : '';
$userRoleType = $currentUserContext !== null ? ($currentUserContext->roleType ?? '') : '';

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html
    lang="en"
    class="<?= $e(implode(' ', $htmlClasses)) ?>"
    dir="ltr"
    data-skin="default"
    data-assets-path="<?= $e($assetBase . '/') ?>"
    data-template="vertical-menu-template"
    data-bs-theme="light">

<head>
    <script>
        try {
            var t = localStorage.getItem('templateCustomizer-' + (document.documentElement.getAttribute('data-template') || 'vertical-menu-template') + '--Theme');
            if (t === 'dark' || (t !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        } catch (_) {}
    </script>
    <meta charset="utf-8">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <meta name="app-context" content="authenticated">
    <meta name="org-uuid" content="<?= $e($organizationUuid) ?>">
    <meta name="user-id" content="<?= $e((string) $userId) ?>">
    <meta name="user-role" content="<?= $e($userRoleType) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>WorkEddy | <?= $title ?></title>

    <link rel="icon" type="image/png" href="<?= $asset('img/favicon.ico') ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Foundation (local assets) -->
    <link href="<?= $asset('css/core.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="<?= $asset('css/app.css') ?>" rel="stylesheet">
    <?php foreach ($pageCss as $css): ?>
        <link rel="stylesheet" href="<?= $asset((string) $css) ?>">
    <?php endforeach; ?>
</head>

<body>

    <div class="layout-wrapper layout-content-navbar<?= !$showSidebar ? ' layout-without-menu' : '' ?>">
        <div class="layout-container">
            <!-- Sidebar Partial -->
            <?php if ($showSidebar): ?>
                <?php require __DIR__ . '/../Partials/sidebar.php'; ?>
            <?php endif; ?>

            <!-- Mobile sidebar overlay -->
            <div class="layout-overlay" id="layoutOverlay"></div>

            <!-- Main Page Wrapper -->
            <div class="layout-page">

                <!-- Top Navbar Partial -->
                <?php require __DIR__ . '/../Partials/navbar.php'; ?>

                <!-- Content Area -->
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y" style="margin-top:5.0rem;">
                        <?= $content ?>
                    </div>

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="mb-2 mb-md-0">
                                    ©
                                    <script>
                                        document.write(new Date().getFullYear())
                                    </script>
                                    WorkEddy
                                </div>
                                <div class="d-none d-lg-inline-block">
                                    <a href="#" target="_blank" class="footer-link d-none d-sm-inline-block">Unlock ICT.</a>
                                </div>
                            </div>
                        </div>
                    </footer>
                </div>

            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation (Visible on screen size md and down) -->
    <nav class="bottom-nav d-lg-none">
        <a href="/dashboard" class="bottom-nav-item<?= str_contains($currentView, 'dashboard') ? ' active' : '' ?>">
            <i class="bi bi-grid-1x2"></i><span>Home</span>
        </a>
        <a href="/tasks" class="bottom-nav-item<?= str_contains($currentView, 'task') ? ' active' : '' ?>">
            <i class="bi bi-list-task"></i><span>Tasks</span>
        </a>
        <a href="/assessments" class="bottom-nav-item<?= str_contains($currentView, 'assessment') ? ' active' : '' ?>">
            <i class="bi bi-clipboard-pulse"></i><span>Assess</span>
        </a>
        <a href="/profile" class="bottom-nav-item<?= str_contains($currentView, 'profile') ? ' active' : '' ?>">
            <i class="bi bi-person"></i><span>Profile</span>
        </a>
    </nav>

    <!-- System Toast Container -->
    <div class="toast-container toast-container-fixed position-fixed top-0 end-0 p-3" id="app-toast-container" aria-live="polite" aria-atomic="true"></div>

    <!-- System Confirmation & Alert Dialog -->
    <?php require __DIR__ . '/../Partials/system_dialog.php'; ?>

    <!-- JS Assets (all local — no CDN) -->
    <script src="<?= $asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= $asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
    <script src="<?= $asset('js/app.js') ?>"></script>
    <script src="<?= $asset('js/shell.js') ?>"></script>

    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= $asset((string) $script) ?>"></script>
    <?php endforeach; ?>

</body>

</html>