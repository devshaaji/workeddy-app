<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Assign Role Permissions';
$pagePurpose = 'Apply catalog permissions to a role with grouped review.';
$roleId = (string) ($routeParams['id'] ?? '');
$pageActions = [
    ['label' => 'Back to Roles', 'url' => '/roles', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$role = $role ?? [];
$can = $can ?? [];
$display = static fn(mixed $value): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : '--'), ENT_QUOTES, 'UTF-8');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row g-4" data-iam-screen="role-permissions">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="card-title mb-1">Permission Catalog</h3>
                    <div class="text-secondary">Code-owned permissions grouped for role assignment.</div>
                </div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="show_selected">
                    <label class="form-check-label" for="show_selected">Show selected only</label>
                </div>
            </div>
            <div class="card-body">
                <form class="row g-2 align-items-end mb-3" data-iam-filters>
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="iam-role-assign-search">Search permissions</label>
                        <input type="search" class="form-control" id="iam-role-assign-search" name="search" placeholder="Permission name or slug" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="iam-role-assign-module">Module</label>
                        <select class="form-select" id="iam-role-assign-module" name="module">
                            <option value="">All modules</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="iam-role-assign-risk">Risk</label>
                        <select class="form-select" id="iam-role-assign-risk" name="risk">
                            <option value="">All risks</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button type="button" class="btn btn-outline-secondary" data-iam-reset>Reset</button>
                    </div>
                </form>
                <div id="iam-role-assign-feedback" class="d-none mb-4" data-form-feedback></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Assign</th>
                                <th>Module</th>
                                <th>Permission</th>
                                <th>Slug</th>
                                <th>Action Category</th>
                                <th>Risk</th>
                                <th>System Only</th>
                            </tr>
                        </thead>
                        <tbody id="iam-role-assign-permissions" data-iam-table-body data-empty-colspan="7">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    Catalog rows appear here when available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="pagination m-0 justify-content-end" id="iam-role-assign-pagination" data-iam-pagination></ul>
                <?php if (!empty($can['manageRoles'])): ?>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-outline-secondary me-2" id="iam-role-assign-reset" data-role-id="<?= $roleId ?>">Reset Changes</button>
                        <button type="button" class="btn btn-primary" id="iam-role-assign-save" data-role-id="<?= $roleId ?>">Save Permissions</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Assignment Summary</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-6">Role</dt>
                    <dd class="col-6 text-end" id="iam-role-assign-name"><?= $display($role['name'] ?? null) ?></dd>
                    <dt class="col-6">Selected</dt>
                    <dd class="col-6 text-end" id="iam-role-assign-count"><?= $display($role['permissionCount'] ?? null) ?></dd>
                    <dt class="col-6">System Only</dt>
                    <dd class="col-6 text-end" id="iam-role-assign-system"><?= !empty($role['isSystem']) ? 'Yes' : 'No' ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
