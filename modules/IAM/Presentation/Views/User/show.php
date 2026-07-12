<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'User Detail';
$pagePurpose = 'View';
$userId = (string) ($routeParams['id'] ?? '');
$pageActions = [
    ['label' => 'Back to Users', 'url' => '/users', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$user = $user ?? [];
$can = $can ?? [];
$profile = $user['profile'] ?? [];
$membership = $user['membership'] ?? [];
$roleLabel = $membership['roleName'] ?? $membership['roleSlug'] ?? null;
$accountStatus = $profile['status'] ?? null;
$lastLoginAt = $profile['lastLoginAt'] ?? null;
$createdAt = $profile['createdAt'] ?? null;
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$display = static fn(mixed $value): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : '--'), ENT_QUOTES, 'UTF-8');
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr($parts[0] ?? '-', 0, 1) . substr($parts[1] ?? '', 0, 1)) ?: '--';
};
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row g-4" data-iam-screen="user-detail">
    <div class="col-xl-4 col-lg-5">
        <div class="card mb-4">
            <div class="card-body pt-5">
                <div class="d-flex align-items-center flex-column text-center">
                    <div class="avatar avatar-xl mb-3 bg-primary text-white">
                        <?= $e($initials((string) ($profile['fullName'] ?? ''))) ?>
                    </div>
                    <h5 class="mb-1" id="iam-user-name"><?= $display($profile['fullName'] ?? null) ?></h5>
                    <span class="badge bg-secondary-lt" id="iam-user-role-badge"><?= $display($roleLabel) ?></span>
                </div>
                <div class="d-flex justify-content-around flex-wrap my-4">
                    <div class="text-center">
                        <h5 class="mb-0" id="iam-user-permission-count">--</h5>
                        <small class="text-muted">Permissions</small>
                    </div>
                    <div class="text-center">
                        <h5 class="mb-0" id="iam-user-override-count">--</h5>
                        <small class="text-muted">Overrides</small>
                    </div>
                    <div class="text-center">
                        <h5 class="mb-0" id="iam-user-session-count">--</h5>
                        <small class="text-muted">Sessions</small>
                    </div>
                </div>
                <h6 class="border-top pt-3 mb-3">Account Metadata</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><span class="fw-medium">Email:</span> <span id="iam-user-email"><?= $display($user['email'] ?? null) ?></span></li>
                    <li class="mb-2"><span class="fw-medium">Phone:</span> <span id="iam-user-phone"><?= $display($profile['phone'] ?? null) ?></span></li>
                    <li class="mb-2"><span class="fw-medium">Status:</span> <span id="iam-user-status"><?= $display($accountStatus) ?></span></li>
                    <li class="mb-2"><span class="fw-medium">Last Login:</span> <span id="iam-user-last-login"><?= $display($lastLoginAt) ?></span></li>
                    <li><span class="fw-medium">Created:</span> <span id="iam-user-created"><?= $display($createdAt) ?></span></li>
                </ul>
                <div class="d-grid gap-2">
                    <?php if (!empty($can['updateUsers'])): ?><a href="/users/<?= $userId ?>/edit" class="btn btn-primary">Edit User</a><?php endif; ?>
                    <?php if (!empty($can['assignRoles'])): ?><a href="/users/<?= $userId ?>/role" class="btn btn-outline-secondary">Assign Role</a><?php endif; ?>
                    <?php if (!empty($can['viewUsers'])): ?><a href="/users/<?= $userId ?>/security" class="btn btn-outline-secondary">Open Security</a><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8 col-lg-7">
        <div class="nav-align-top mb-4">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active" href="#iam-user-permissions-section">Permissions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#iam-user-overrides-section">Overrides</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#iam-user-history-section">History</a>
                </li>
            </ul>
        </div>

        <div class="card mb-4" id="iam-user-permissions-section">
            <div class="card-header">
                <h3 class="card-title mb-0">Effective Permissions</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Permission</th>
                                <th>Action Category</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody id="iam-user-permissions-body" data-iam-table-body data-empty-colspan="4">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Effective permission rows appear here when available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4" id="iam-user-overrides-section">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="card-title mb-1">Permission Overrides</h3>
                    <div class="text-secondary">Role-default differences and user-level grants or denies are reviewed here.</div>
                </div>
                <a href="/users/<?= $userId ?>/security" class="btn btn-outline-secondary">Review Overrides</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Permission</th>
                                <th>Role Default</th>
                                <th>Override</th>
                                <th>Effective Result</th>
                            </tr>
                        </thead>
                        <tbody id="iam-user-overrides-body" data-iam-table-body data-empty-colspan="5">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Override rows appear here when direct user permission changes exist.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="iam-user-history-section">
            <?php require $v2Root . '/shared/Views/Partials/timeline_shell.php'; ?>
        </div>
    </div>
</div>
