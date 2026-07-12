<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Users';
$pagePurpose = 'Manage staff accounts, partner access, account status, and role readiness.';
$pageActions = [
    ['label' => 'Pending Approvals', 'url' => '/users/pending-approvals', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$can = $can ?? [];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" id="iam-users-directory" data-iam-screen="users" data-empty-message="No users loaded yet. User rows will appear here after the list is connected.">
    <div class="card-body border-bottom">
        <div id="iam-users-feedback" class="d-none mb-3" data-form-feedback></div>
        <form class="row g-2 align-items-end" data-iam-filters>
            <div class="col-12 col-md-5">
                <label class="form-label" for="iam-users-search">Search users</label>
                <input type="search" class="form-control" id="iam-users-search" name="search" placeholder="Name, email, or username" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="iam-users-status">Status</label>
                <select class="form-select" id="iam-users-status" name="status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="iam-users-role">Role</label>
                <select class="form-select" id="iam-users-role" name="role">
                    <option value="">All roles</option>
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
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="iam-users-table-body" data-iam-table-body data-empty-colspan="7">
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        No users loaded yet. User rows, pagination state, and row-level actions appear here after binding.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center">
        <ul class="pagination m-0 ms-auto" id="iam-users-pagination" data-iam-pagination></ul>
    </div>
</div>