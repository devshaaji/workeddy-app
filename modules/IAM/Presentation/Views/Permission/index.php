<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Permissions';
$pagePurpose = 'Inspect the system permission catalog and how it maps to roles.';
$pageScripts = ['js/iam.js'];
$can = $can ?? [];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" data-iam-screen="permissions" data-empty-message="No permissions loaded yet. Permission catalog rows will appear here after binding.">
    <div class="card-body border-bottom">
        <div id="iam-permissions-feedback" class="d-none mb-3" data-form-feedback></div>
        <form class="row g-2 align-items-end" data-iam-filters>
            <div class="col-12 col-md-5">
                <label class="form-label" for="iam-permissions-search">Search permissions</label>
                <input type="search" class="form-control" id="iam-permissions-search" name="search" placeholder="Permission name or slug" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="iam-permissions-module">Module</label>
                <select class="form-select" id="iam-permissions-module" name="module">
                    <option value="">All modules</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="iam-permissions-risk">Risk</label>
                <select class="form-select" id="iam-permissions-risk" name="risk">
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
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 72px;">S/N</th>
                    <th>Module</th>
                    <th>Permission</th>
                    <th>Slug</th>
                    <th>Action Category</th>
                    <th>Risk</th>
                    <th>Default Assignments</th>
                    <th>System Only</th>
                </tr>
            </thead>
            <tbody id="iam-permissions-body" data-iam-table-body data-empty-colspan="8">
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        No permissions loaded yet. Permission catalog rows appear here after binding.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center">
        <ul class="pagination m-0 ms-auto" id="iam-permissions-pagination" data-iam-pagination></ul>
    </div>
</div>