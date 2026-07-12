<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Create Role';
$pagePurpose = 'Define a staff role and its permission set.';
$pageActions = [
    ['label' => 'Back to Roles', 'url' => '/roles', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$can = $can ?? [];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row g-4" data-iam-screen="role-create">
<div class="col-12">
<div class="card mb-4">
    <div class="card-header">
                <h3 class="card-title mb-0">Role Identity</h3>
    </div>
    <div class="card-body">
        <form id="iam-role-create-form" action="/api/v1/iam/roles" method="post" autocomplete="off" data-iam-form="role-create">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="role_name">Role name</label>
                    <input type="text" id="role_name" name="role_name" class="form-control" placeholder="Enter a role name">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="role_slug">Role slug</label>
                    <input type="text" id="role_slug" name="role_slug" class="form-control" placeholder="role_slug">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="role_scope">Role scope</label>
                    <select id="role_scope" name="role_scope" class="form-select">
                        <option value="staff" selected>Staff</option>
                        <option value="customer">Customer</option>
                        <option value="system">System</option>
                    </select>
                    <div class="form-hint">HRM can assign only staff-scoped roles to employees.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="role_description">Description</label>
                    <textarea id="role_description" name="role_description" class="form-control" rows="3" placeholder="Describe the role purpose and intended users"></textarea>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="card-title mb-1">Permission Selection</h3>
            <div class="text-secondary">Select the permissions that define this role.</div>
        </div>
        <?php if (!empty($can['manageRoles'])): ?><button type="button" class="btn btn-outline-secondary" id="iam-role-create-select-all" data-iam-select-all>Select All Assignable</button><?php endif; ?>
    </div>
    <div class="card-body">
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
                <tbody id="iam-role-create-permissions" data-iam-table-body data-empty-colspan="6">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            Permission rows appear here when available.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ul class="pagination m-0 justify-content-end" id="iam-role-create-pagination" data-iam-pagination></ul>
        <div id="iam-role-create-feedback" class="d-none mt-4" data-form-feedback></div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <a href="/roles" class="btn btn-outline-secondary">Cancel</a>
            <?php if (!empty($can['manageRoles'])): ?><button type="submit" form="iam-role-create-form" class="btn btn-primary">Create Role</button><?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
