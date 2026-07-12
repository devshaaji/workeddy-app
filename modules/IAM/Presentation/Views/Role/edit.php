<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Edit Role';
$pagePurpose = 'Adjust a role identity and permission set with audit awareness.';
$roleId = (string) ($routeParams['id'] ?? '');
$pageActions = [
    ['label' => 'Back to Roles', 'url' => '/roles', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$role = $role ?? [];
$can = $can ?? [];
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row g-4" data-iam-screen="role-edit">
    <div class="col-xl-12">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Role Identity</h3>
            </div>
            <div class="card-body">
                <form id="iam-role-edit-form" action="/api/v1/iam/roles/<?= $roleId ?>" method="post" data-role-id="<?= $roleId ?>" autocomplete="off" data-iam-form="role-edit">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="role_name">Role name</label>
                            <input type="text" id="role_name" name="role_name" class="form-control" value="<?= $e($role['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="role_slug">Role slug</label>
                            <input type="text" id="role_slug" name="role_slug" class="form-control" value="<?= $e($role['slug'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="role_scope">Role scope</label>
                            <select id="role_scope" name="role_scope" class="form-select">
                                <?php foreach (['staff' => 'Staff', 'customer' => 'Customer', 'system' => 'System'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($role['scope'] ?? 'staff') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">HRM can assign only staff-scoped roles to employees.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?= $e($role['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="card-title mb-1">Permission Changes</h3>
                    <div class="text-secondary">Review and adjust the permissions assigned to this role.</div>
                </div>
                <span class="badge bg-info-lt" id="iam-role-edit-type"><?= !empty($role['isSystem']) ? 'System' : 'Custom' ?></span>
            </div>
            <div class="card-body">
                <div id="iam-role-edit-feedback" class="d-none mb-4" data-form-feedback></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Assign</th>
                                <th>Module</th>
                                <th>Permission</th>
                                <th>Action Category</th>
                                <th>Risk</th>
                                <th>System Only</th>
                            </tr>
                        </thead>
                        <tbody id="iam-role-edit-permissions" data-iam-table-body data-empty-colspan="6">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Permission rows appear here when available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="pagination m-0 justify-content-end" id="iam-role-edit-pagination" data-iam-pagination></ul>
                <div class="text-end mt-4">
                    <a href="/roles/<?= $roleId ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                    <?php if (!empty($can['manageRoles'])): ?><button type="submit" form="iam-role-edit-form" class="btn btn-primary">Save Changes</button><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
