<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Role Detail';
$pagePurpose = 'Review role membership, effective permissions, and change history.';
$pageActions = [
    ['label' => 'Back to Roles', 'url' => '/roles', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$role = $role ?? [];
$can = $can ?? [];
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$display = static fn(mixed $value): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : '--'), ENT_QUOTES, 'UTF-8');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row g-4" data-iam-screen="role-detail">
    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="avatar avatar-lg me-3">
                        <span class="avatar bg-primary text-white" id="iam-role-initials"><?= $e(strtoupper(substr((string) ($role['name'] ?? '-'), 0, 2))) ?></span>
                    </div>
                    <div>
                        <h5 class="mb-1" id="iam-role-name"><?= $display($role['name'] ?? null) ?></h5>
                        <small class="text-muted" id="iam-role-slug"><?= $display($role['slug'] ?? null) ?></small>
                    </div>
                </div>
                <p class="text-muted mb-4" id="iam-role-description"><?= $display($role['description'] ?? 'Role description appears here when available.') ?></p>
                <dl class="row mb-0">
                    <dt class="col-6">Users</dt>
                    <dd class="col-6 text-end" id="iam-role-users"><?= $display($role['userCount'] ?? null) ?></dd>
                    <dt class="col-6">Permissions</dt>
                    <dd class="col-6 text-end" id="iam-role-permissions"><?= $display($role['permissionCount'] ?? null) ?></dd>
                    <dt class="col-6">Risk</dt>
                    <dd class="col-6 text-end"><span class="badge bg-secondary-lt" id="iam-role-risk"><?= $display($role['riskLevel'] ?? null) ?></span></dd>
                    <dt class="col-6">System</dt>
                    <dd class="col-6 text-end" id="iam-role-system"><?= !empty($role['isSystem']) ? 'Yes' : 'No' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Assigned Users</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="iam-role-users-body" data-iam-table-body data-empty-colspan="2">
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">
                                    Assigned users appear here when available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="pagination m-0 justify-content-end" id="iam-role-users-pagination" data-iam-pagination></ul>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Permission Coverage</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Permission</th>
                                <th>Action Category</th>
                                <th>Risk</th>
                            </tr>
                        </thead>
                        <tbody id="iam-role-permissions-body" data-iam-table-body data-empty-colspan="4">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Permission rows appear here when available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="pagination m-0 justify-content-end" id="iam-role-permissions-pagination" data-iam-pagination></ul>
            </div>
        </div>

        <?php require $v2Root . '/shared/Views/Partials/timeline_shell.php'; ?>
    </div>
</div>
