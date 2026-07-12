<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Roles';
$pagePurpose = 'Maintain reusable access profiles for platform users.';
$pageScripts = ['js/iam.js'];
$can = $can ?? [];
if (!empty($can['manageRoles'])) {
    $pageActions = [
        ['label' => 'Add New Role', 'url' => '/roles/new', 'class' => 'btn btn-primary'],
    ];
}
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" id="iam-roles-list" data-iam-screen="roles" data-empty-message="No roles loaded yet. Role rows will appear here after the list is connected.">
    <div class="card-body border-bottom">
        <div id="iam-roles-feedback" class="d-none mb-3" data-form-feedback></div>
        <form class="row g-2 align-items-end" data-iam-filters>
            <div class="col-12 col-md-5">
                <label class="form-label" for="iam-roles-search">Search roles</label>
                <input type="search" class="form-control" id="iam-roles-search" name="search" placeholder="Role name or description" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="iam-roles-risk">Risk</label>
                <select class="form-select" id="iam-roles-risk" name="risk">
                    <option value="">All risk levels</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="iam-roles-system">System role</label>
                <select class="form-select" id="iam-roles-system" name="system">
                    <option value="">All roles</option>
                    <option value="yes">System roles</option>
                    <option value="no">Custom roles</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-grid">
                <button type="button" class="btn btn-outline-secondary" data-iam-reset>Reset</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 72px;">S/N</th>
                    <th>Role</th>
                    <th>Description</th>
                    <th>System</th>
                    <th>Risk</th>
                    <th>Users</th>
                    <th>Permissions</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="iam-roles-body" data-iam-table-body data-empty-colspan="8">
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        No roles loaded yet. Role rows and row-level actions appear here after binding.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center">
        <ul class="pagination m-0 ms-auto" id="iam-roles-pagination" data-iam-pagination></ul>
    </div>
</div>
