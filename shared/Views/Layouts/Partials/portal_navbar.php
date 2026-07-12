<?php

declare(strict_types=1);

/**
 * portal_navbar.php
 * ─────────────────────────────────────────────────────────────────
 * Customer-facing topbar for the Customer Portal shell.
 * Contains: brand, portal label, customer avatar/name, logout dropdown.
 * Does NOT include: admin navigation, notification bell, admin links.
 * ─────────────────────────────────────────────────────────────────
 */

$currentUserContext = $currentUserContext ?? null;
$portalCustomerName = '';
$portalInitials     = '';

if ($currentUserContext instanceof \WorkEddy\Platform\Session\UserContext) {
    // Prefer a display name set by the page data, fall back to user ID
    $portalCustomerName = trim((string) ($portalDisplayName ?? ('Customer #' . $currentUserContext->userId)));
} else {
    $portalCustomerName = 'Customer';
}

// Build 1–2 character initials from the display name
$nameParts = preg_split('/\s+/', $portalCustomerName) ?: [];
foreach ($nameParts as $part) {
    if ($part !== '') {
        $portalInitials .= strtoupper(substr($part, 0, 1));
    }
    if (strlen($portalInitials) >= 2) {
        break;
    }
}
$portalInitials = $portalInitials !== '' ? $portalInitials : 'C';

$portalNavItems = [
    ['label' => 'Dashboard',      'url' => '/portal',               'active' => str_ends_with($currentView ?? '', 'dashboard.php')],
    ['label' => 'Invoices',       'url' => '/portal/invoices',      'active' => str_ends_with($currentView ?? '', 'invoices.php')],
    ['label' => 'Subscriptions',  'url' => '/portal/subscriptions', 'active' => str_ends_with($currentView ?? '', 'subscriptions.php')],
    ['label' => 'Installations',  'url' => '/portal/installations', 'active' => str_ends_with($currentView ?? '', 'installations.php')],
    ['label' => 'Support',        'url' => '/portal/tickets',       'active' => str_ends_with($currentView ?? '', 'tickets.php')],
];
?>
<header class="navbar portal-navbar navbar-expand-md d-print-none">
    <div class="container-xl">

        <!-- Brand -->
        <a href="/portal" class="portal-brand me-3" aria-label="Customer Portal home">
            <span class="portal-brand-logo">MX</span>
            <span class="portal-brand-label">Customer<br>Portal</span>
        </a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler ms-auto" type="button"
            data-bs-toggle="collapse" data-bs-target="#portal-nav-menu"
            aria-controls="portal-nav-menu" aria-expanded="false"
            aria-label="Toggle portal navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Portal nav links -->
        <div class="collapse navbar-collapse" id="portal-nav-menu">
            <ul class="navbar-nav me-auto">
                <?php foreach ($portalNavItems as $item): ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link<?= $item['active'] ? ' active' : '' ?>">
                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- User dropdown -->
            <div class="navbar-nav flex-row ms-auto align-items-center">
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex align-items-center lh-1 text-reset p-0 gap-2"
                        data-bs-toggle="dropdown" aria-label="Open account menu" id="portal-user-menu">
                        <span class="avatar avatar-sm bg-blue-lt">
                            <?= htmlspecialchars($portalInitials, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="d-none d-xl-block">
                            <div class="fw-medium small"><?= htmlspecialchars($portalCustomerName, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-secondary" style="font-size:0.7rem;">Customer account</div>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow" aria-labelledby="portal-user-menu">
                        <a href="/profile" class="dropdown-item">My profile</a>
                        <a href="/profile/security" class="dropdown-item">Password &amp; security</a>
                        <div class="dropdown-divider"></div>
                        <a href="/logout" class="dropdown-item text-danger" data-app-logout>Sign out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>