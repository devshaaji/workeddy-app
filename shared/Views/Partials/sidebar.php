<?php

declare(strict_types=1);

use WorkEddy\Modules\Export\Authorization\ExportPermissions;
use WorkEddy\Modules\Finance\Authorization\FinancePermissions;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\Content\Authorization\ContentPermissions;

/**
 * WorkEddy v2 — Sneat-styled sidebar navigation.
 *
 * Matches the Sneat Bootstrap vertical menu template structure:
 *   layout-menu menu-vertical menu bg-menu-theme
 *   app-brand.demo > app-brand-link > app-brand-logo + app-brand-text
 *   menu-inner-shadow
 *   menu-inner > menu-item > menu-link / menu-toggle + menu-sub
 *
 * Available variables (from layout scope):
 *   $currentView        string  – e.g. modules/Assessment/Presentation/Views/index.php
 *   $currentUserContext ?UserContext
 *   $activePage         ?string – override active nav state
 */

$currentView ??= '';
$currentUserContext ??= null;
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$activeNavKeys = array_values(array_filter([
    is_string($activePage ?? null) ? $activePage : null,
    $currentView,
    is_string($requestPath) ? $requestPath : null,
], static fn(mixed $value): bool => is_string($value) && $value !== ''));

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Check whether the current page matches a key fragment. */
$activeNav = static function (string $key) use ($activeNavKeys): bool {
    foreach ($activeNavKeys as $activeNavKey) {
        if (str_contains($activeNavKey, $key)) {
            return true;
        }
    }

    return false;
};

/** Check whether the current page matches ANY of several key fragments. */
$activeAny = static function (array $keys) use ($activeNavKeys): bool {
    foreach ($keys as $k) {
        foreach ($activeNavKeys as $activeNavKey) {
            if (str_contains($activeNavKey, $k)) {
                return true;
            }
        }
    }
    return false;
};

/** Permission-gate helper — checks if the user holds a specific permission. */
$can = static function (string $permission) use ($currentUserContext): bool {
    if ($currentUserContext === null) {
        return false;
    }
    return $currentUserContext->hasPermission($permission);
};

/** Permission-gate helper — checks if the user holds ANY of several permissions. */
$canAny = static function (array $permissions) use ($currentUserContext): bool {
    if ($currentUserContext === null) {
        return false;
    }
    foreach ($permissions as $p) {
        if ($currentUserContext->hasPermission($p)) {
            return true;
        }
    }
    return false;
};

$organizationUuid = $currentUserContext?->organizationUuid ?? '';
$orgBase = $organizationUuid ? "/organizations/$organizationUuid" : '#';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

    <!-- Brand -->
    <div class="app-brand app-brand-demo">
        <a href="/dashboard" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="/assets/img/logo.png" alt="WorkEddy" style="max-height: 32px; width: auto;" />
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-2">WorkEddy</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto" id="sidebarDesktopToggle" aria-label="Collapse menu">
            <i class="bi bi-chevron-left"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <!-- Menu -->
    <ul class="menu-inner py-1">

        <!-- ═══════════ MAIN ═══════════ -->
        <li class="menu-header small text-uppercase text-muted fw-semibold">Main</li>

        <li class="menu-item<?= $activeNav('dashboard') ? ' active' : '' ?>">
            <a class="menu-link" href="/dashboard">
                <i class="menu-icon icon-base bi bi-grid-1x2"></i>
                <div data-i18n="Dashboard">Dashboard</div>
            </a>
        </li>

        <!-- ═══════════ SAFETY ═══════════ -->
        <li class="menu-header small text-uppercase text-muted fw-semibold">Safety</li>

        <!-- Assessments — dropdown -->
        <?php $assessOpen = $activeAny(['assessment', 'new-manual', 'reviewer-queue', 'heatmap', 'video-evidence']); ?>
        <li class="menu-item<?= $assessOpen ? ' active open' : '' ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base bi bi-clipboard-pulse"></i>
                <div data-i18n="Assessments">Assessments</div>

            </a>
            <ul class="menu-sub<?= $assessOpen ? '' : ' d-none' ?>">
                <li class="menu-item<?= $activeNav('assessments') && !$activeNav('new-manual') && !$activeNav('reviewer-queue') ? ' active' : '' ?>">
                    <a class="menu-link" href="/assessments">
                        All Assessments
                    </a>
                </li>
                <li class="menu-item<?= $activeNav('new-manual') ? ' active' : '' ?>">
                    <a class="menu-link" href="/assessments/new-manual">
                        New Manual
                    </a>
                </li>
                <li class="menu-item<?= $activeNav('video') ? ' active' : '' ?>">
                    <a class="menu-link" href="/assessments/video">
                        Capture Video
                    </a>
                </li>
                <li class="menu-item<?= $activeNav('reviewer-queue') ? ' active' : '' ?>">
                    <a class="menu-link" href="/assessments/reviewer-queue">
                        Review Queue
                    </a>
                </li>
            </ul>
        </li>

        <!-- Corrective Actions — dropdown -->
        <?php $caOpen = $activeAny(['CorrectiveAction', 'corrective-action', 'recommendations']); ?>
        <li class="menu-item<?= $caOpen ? ' active open' : '' ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base bi bi-check2-square"></i>
                <div data-i18n="Corrective Actions">Corrective Actions</div>

            </a>
            <ul class="menu-sub<?= $caOpen ? '' : ' d-none' ?>">
                <li class="menu-item<?= $activeNav('controls') ? ' active' : '' ?>">
                    <a class="menu-link" href="/corrective-actions/controls">
                        Controls
                    </a>
                </li>
                <li class="menu-item<?= ($activeNav('CorrectiveAction') || $activeNav('corrective-action')) && !$activeNav('recommendations') && !$activeNav('controls') ? ' active' : '' ?>">
                    <a class="menu-link" href="/corrective-actions">
                        Actions
                    </a>
                </li>
                <li class="menu-item<?= $activeNav('recommendations') ? ' active' : '' ?>">
                    <a class="menu-link" href="/corrective-actions/recommendations">
                        Recommendations
                    </a>
                </li>
            </ul>
        </li>

        <!-- Tasks — single link -->
        <li class="menu-item<?= $activeNav('task') ? ' active' : '' ?>">
            <a class="menu-link" href="/tasks">
                <i class="menu-icon icon-base bi bi-list-task"></i>
                <div data-i18n="Tasks">Tasks</div>
            </a>
        </li>

        <?php if ($canAny([WorkerVoicePermissions::SUBMIT, WorkerVoicePermissions::VIEW, WorkerVoicePermissions::VIEW_AGGREGATES])): ?>
            <?php $workerVoiceOpen = $activeAny(['worker-voice', 'worker_voice']); ?>
            <li class="menu-item<?= $workerVoiceOpen ? ' active open' : '' ?>">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base bi bi-chat-square-text"></i>
                    <div data-i18n="Worker Voice">Worker Voice</div>
                </a>
                <ul class="menu-sub<?= $workerVoiceOpen ? '' : ' d-none' ?>">
                    <?php if ($canAny([WorkerVoicePermissions::VIEW, WorkerVoicePermissions::VIEW_AGGREGATES])): ?>
                        <li class="menu-item<?= $activeNav('/worker-voice') && !$activeNav('/worker-voice/new') && !$activeNav('/worker-voice/supervisor') && !$activeNav('/worker-voice/trends') ? ' active' : '' ?>">
                            <a class="menu-link" href="/worker-voice">
                                Register
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($can(WorkerVoicePermissions::SUBMIT)): ?>
                        <li class="menu-item<?= $activeNav('/worker-voice/new') ? ' active' : '' ?>">
                            <a class="menu-link" href="/worker-voice/new">
                                Submit Feedback
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('/worker-voice/supervisor/new') ? ' active' : '' ?>">
                            <a class="menu-link" href="/worker-voice/supervisor/new">
                                Supervisor Feedback
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($can(WorkerVoicePermissions::VIEW_AGGREGATES)): ?>
                        <li class="menu-item<?= $activeNav('/worker-voice/trends') ? ' active' : '' ?>">
                            <a class="menu-link" href="/worker-voice/trends">
                                Trends
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('/worker-voice/supervisor/trends') ? ' active' : '' ?>">
                            <a class="menu-link" href="/worker-voice/supervisor/trends">
                                Supervisor Trends
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <!-- ═══════════ INSIGHTS ═══════════ -->
        <li class="menu-header small text-uppercase text-muted fw-semibold">Insights</li>

        <!-- Reporting — dropdown -->
        <?php if ($canAny([ReportingPermissions::VIEW, ReportingPermissions::SYSTEM_VIEW, 'audit.view'])): ?>
            <?php $rptOpen = $activeAny(['reporting', 'audit']); ?>
            <li class="menu-item<?= $rptOpen ? ' active open' : '' ?>">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base bi bi-bar-chart-line"></i>
                    <div data-i18n="Reports">Reports</div>

                </a>
                <ul class="menu-sub<?= $rptOpen ? '' : ' d-none' ?>">
                    <?php if ($can(ReportingPermissions::VIEW)): ?>
                        <li class="menu-item<?= $activeNav('reporting/pilot-summary') ? ' active' : '' ?>">
                            <a class="menu-link" href="/reporting/pilot-summary">
                                Pilot Summary
                            </a>
                        </li>
                        <li class="menu-item<?= $activeAny(['modules/Reporting/Presentation/Views/Assessment', '/assessments']) && !$activeAny(['comparison', 'reviewer-queue', 'new-manual', 'video']) ? ' active' : '' ?>">
                            <a class="menu-link" href="/assessments">
                                Assessment Reports
                            </a>
                        </li>
                        <li class="menu-item<?= $activeAny(['modules/Reporting/Presentation/Views/Corrective-action', '/corrective-actions']) ? ' active' : '' ?>">
                            <a class="menu-link" href="/corrective-actions">
                                Corrective Action Reports
                            </a>
                        </li>
                        <li class="menu-item<?= $activeAny(['modules/Reporting/Presentation/Views/Comparison', '/assessments/comparisons', 'comparison-reports']) ? ' active' : '' ?>">
                            <a class="menu-link" href="/assessments/comparisons">
                                Comparison Reports
                            </a>
                        </li>
                        <li class="menu-item<?= $activeAny(['modules/Reporting/Presentation/Views/Audit-trial', '/audit', '/logs']) ? ' active' : '' ?>">
                            <a class="menu-link" href="/audit/logs">
                                Audit Trail Reports
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($can(ReportingPermissions::SYSTEM_VIEW)): ?>
                        <li class="menu-item<?= $activeNav('reporting/dashboard') ? ' active' : '' ?>">
                            <a class="menu-link" href="/reporting/dashboard">
                                System Dashboard
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('reporting/operations') ? ' active' : '' ?>">
                            <a class="menu-link" href="/reporting/operations">
                                Operations
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('reporting/finance') ? ' active' : '' ?>">
                            <a class="menu-link" href="/reporting/finance">
                                Finance
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if ($can(ContentPermissions::PAGES_READ)): ?>
            <li class="menu-item<?= $activeNav('methodology-and-limitations') ? ' active' : '' ?>">
                <a class="menu-link" href="/content/methodology-and-limitations">
                    <i class="menu-icon icon-base bi bi-journal-text"></i>
                    <div data-i18n="Methodology">Methodology</div>
                </a>
            </li>
        <?php endif; ?>

        <!-- ═══════════ WORKSPACE ═══════════ -->
        <?php if ($can(ExportPermissions::VIEW)): ?>
            <li class="menu-item<?= $activeNav('research-exports') ? ' active' : '' ?>">
                <a class="menu-link" href="/research-exports">
                    <i class="menu-icon icon-base bi bi-download"></i>
                    <div data-i18n="Research Exports">Research Exports</div>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($canAny(['organization.view', 'organization.structure.manage', 'organization.members.manage'])): ?>
            <li class="menu-header small text-uppercase text-muted fw-semibold">Workspace</li>

            <li class="menu-item<?= $activeNav('worksite') ? ' active' : '' ?>">
                <a class="menu-link we-org-link" href="<?= $e($orgBase) ?>/worksites">
                    <i class="menu-icon icon-base bi bi-building"></i>
                    <div data-i18n="Worksites">Worksites</div>
                </a>
            </li>

            <li class="menu-item<?= $activeNav('department') ? ' active' : '' ?>">
                <a class="menu-link we-org-link" href="<?= $e($orgBase) ?>/departments">
                    <i class="menu-icon icon-base bi bi-diagram-3"></i>
                    <div data-i18n="Departments">Departments</div>
                </a>
            </li>

            <li class="menu-item<?= ($activeNav('job.role') || $activeNav('job-role')) ? ' active' : '' ?>">
                <a class="menu-link we-org-link" href="<?= $e($orgBase) ?>/job-roles">
                    <i class="menu-icon icon-base bi bi-person-badge"></i>
                    <div data-i18n="Job Roles">Job Roles</div>
                </a>
            </li>

            <li class="menu-item<?= $activeNav('member') ? ' active' : '' ?>">
                <a class="menu-link we-org-link" href="<?= $e($orgBase) ?>/members">
                    <i class="menu-icon icon-base bi bi-people"></i>
                    <div data-i18n="Team Members">Team Members</div>
                </a>
            </li>
        <?php endif; ?>

        <!-- ═══════════ ACCOUNT ═══════════ -->
        <li class="menu-header small text-uppercase text-muted fw-semibold">Account</li>

        <li class="menu-item<?= $activeNav('profile') ? ' active' : '' ?>">
            <a class="menu-link" href="/profile">
                <i class="menu-icon icon-base bi bi-person-circle"></i>
                <div data-i18n="My Profile">My Profile</div>
            </a>
        </li>

        <li class="menu-item<?= $activeNav('subscription') ? ' active' : '' ?>">
            <a class="menu-link" href="/subscriptions">
                <i class="menu-icon icon-base bi bi-credit-card"></i>
                <div data-i18n="Subscription">Subscription</div>
            </a>
        </li>

        <!-- ═══════════ ADMINISTRATION (super_admin + admin) ═══════════ -->
        <?php if ($canAny(['iam.user.view', 'iam.role.manage', 'billing.view_billing', 'notification.log.view'])): ?>
            <li class="menu-header small text-uppercase text-muted fw-semibold">Administration</li>

            <!-- Users & Access — dropdown -->
            <?php if ($canAny(['iam.user.view', 'iam.role.manage', 'iam.permission.assign'])): ?>
                <?php $iamOpen = $activeAny(['users', 'roles', 'permissions']); ?>
                <li class="menu-item<?= $iamOpen ? ' active open' : '' ?>">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon icon-base bi bi-shield-lock"></i>
                        <div data-i18n="Users & Access">Users & Access</div>

                    </a>
                    <ul class="menu-sub<?= $iamOpen ? '' : ' d-none' ?>">
                        <li class="menu-item<?= $activeNav('users') && !$activeNav('pending') ? ' active' : '' ?>">
                            <a class="menu-link" href="/users">
                                All Users
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('pending-approvals') ? ' active' : '' ?>">
                            <a class="menu-link" href="/users/pending-approvals">
                                Pending Approvals
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('roles') ? ' active' : '' ?>">
                            <a class="menu-link" href="/roles">
                                Roles
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('permissions') ? ' active' : '' ?>">
                            <a class="menu-link" href="/permissions">
                                Permissions
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Billing — dropdown -->
            <?php if ($can('billing.view_billing')): ?>
                <?php $billingOpen = $activeAny(['billing', 'invoice', 'quotation']); ?>
                <li class="menu-item<?= $billingOpen ? ' active open' : '' ?>">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon icon-base bi bi-receipt"></i>
                        <div data-i18n="Billing">Billing</div>

                    </a>
                    <ul class="menu-sub<?= $billingOpen ? '' : ' d-none' ?>">
                        <li class="menu-item<?= $activeNav('invoices') ? ' active' : '' ?>">
                            <a class="menu-link" href="/billing/invoices">
                                Invoices
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('quotations') ? ' active' : '' ?>">
                            <a class="menu-link" href="/billing/quotations">
                                Quotations
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Finance — dropdown -->


            <!-- Audit Logs — single link -->


            <!-- Notifications — dropdown -->
            <?php if ($can('notification.log.view')): ?>
                <?php $notifOpen = $activeAny(['Notification', 'notification']); ?>
                <li class="menu-item<?= $notifOpen ? ' active open' : '' ?>">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon icon-base bi bi-bell"></i>
                        <div data-i18n="Notifications">Notifications</div>

                    </a>
                    <ul class="menu-sub<?= $notifOpen ? '' : ' d-none' ?>">
                        <li class="menu-item<?= $activeNav('Views/Log') ? ' active' : '' ?>">
                            <a class="menu-link" href="/notifications/logs">
                                Logs
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('Views/Template') ? ' active' : '' ?>">
                            <a class="menu-link" href="/notifications/templates">
                                Templates
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('Views/Settings') ? ' active' : '' ?>">
                            <a class="menu-link" href="/notifications/settings">
                                Settings
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ═══════════ PLATFORM (super_admin only) ═══════════ -->
        <?php if ($canAny(['organization.manage', 'storage.settings.manage', 'privacy.retention.manage', 'iam.settings.manage', ContentPermissions::PAGES_READ, FinancePermissions::VIEW, FinancePermissions::MANAGE, FinancePermissions::SETTINGS])): ?>
            <li class="menu-header small text-uppercase text-muted fw-semibold">Platform</li>

            <?php if ($can('organization.manage')): ?>
                <li class="menu-item<?= $activeNav('organization') && !$activeNav('worksite') && !$activeNav('department') && !$activeNav('job') && !$activeNav('member') ? ' active' : '' ?>">
                    <a class="menu-link" href="/organizations">
                        <i class="menu-icon icon-base bi bi-building-fill"></i>
                        <div data-i18n="Organizations">Organizations</div>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('storage.settings.manage')): ?>
                <li class="menu-item<?= $activeNav('storage') ? ' active' : '' ?>">
                    <a class="menu-link" href="/storage">
                        <i class="menu-icon icon-base bi bi-cloud-arrow-up"></i>
                        <div data-i18n="Storage">Storage</div>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can(ContentPermissions::PAGES_READ)): ?>
                <?php $contentOpen = $activeAny(['content']); ?>
                <li class="menu-item<?= $contentOpen ? ' active open' : '' ?>">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon icon-base bi bi-file-earmark-richtext"></i>
                        <div data-i18n="Content">Content</div>
                    </a>
                    <ul class="menu-sub<?= $contentOpen ? '' : ' d-none' ?>">
                        <li class="menu-item<?= $activeNav('/content') && !$activeNav('/content/media') && !$activeNav('methodology-and-limitations') ? ' active' : '' ?>">
                            <a class="menu-link" href="/content">
                                All Content
                            </a>
                        </li>
                        <?php if ($can(ContentPermissions::MEDIA_READ)): ?>
                            <li class="menu-item<?= $activeNav('/content/media') ? ' active' : '' ?>">
                                <a class="menu-link" href="/content/media">
                                    Media Library
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($canAny([FinancePermissions::VIEW, FinancePermissions::MANAGE, FinancePermissions::SETTINGS])): ?>
                <?php $finOpen = $activeAny(['finance']); ?>
                <li class="menu-item<?= $finOpen ? ' active open' : '' ?>">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon icon-base bi bi-wallet2"></i>
                        <div data-i18n="Finance">Finance</div>

                    </a>
                    <ul class="menu-sub<?= $finOpen ? '' : ' d-none' ?>">
                        <li class="menu-item<?= $activeNav('finance/dashboard') ? ' active' : '' ?>">
                            <a class="menu-link" href="/finance/dashboard">
                                Overview
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('finance/income') ? ' active' : '' ?>">
                            <a class="menu-link" href="/finance/income">
                                Income
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('finance/expenses') ? ' active' : '' ?>">
                            <a class="menu-link" href="/finance/expenses">
                                Expenses
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('finance/payroll') ? ' active' : '' ?>">
                            <a class="menu-link" href="/finance/payroll">
                                Payroll
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($canAny(['privacy.retention.manage', 'privacy.audit.view'])): ?>
                <?php $privOpen = $activeAny(['privacy', 'consent', 'retention', 'video-access']); ?>
                <li class="menu-item<?= $privOpen ? ' active open' : '' ?>">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon icon-base bi bi-lock"></i>
                        <div data-i18n="Privacy">Privacy</div>

                    </a>
                    <ul class="menu-sub<?= $privOpen ? '' : ' d-none' ?>">
                        <li class="menu-item<?= $activeNav('privacy') && !$activeNav('consent') && !$activeNav('retention') && !$activeNav('video-access') ? ' active' : '' ?>">
                            <a class="menu-link" href="/privacy">
                                Overview
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('consent') ? ' active' : '' ?>">
                            <a class="menu-link" href="/privacy/consent">
                                Consent
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('retention') ? ' active' : '' ?>">
                            <a class="menu-link" href="/privacy/retention">
                                Retention
                            </a>
                        </li>
                        <li class="menu-item<?= $activeNav('video-access-log') ? ' active' : '' ?>">
                            <a class="menu-link" href="/privacy/video-access-log">
                                Video Access Log
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($can('iam.settings.manage')): ?>
                <li class="menu-item<?= ($activeNav('settings/page') || $activeNav('admin/settings')) ? ' active' : '' ?>">
                    <a class="menu-link" href="/settings/page">
                        <i class="menu-icon icon-base bi bi-sliders"></i>
                        <div data-i18n="System Settings">System Settings</div>
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>

    </ul>
</aside>
