<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Pending User Approvals';
$pagePurpose = 'Review partner registrations before granting platform access.';
$pageActions = [
    ['label' => 'All Users', 'url' => '/users', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$can = $can ?? [];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" id="iam-pending-table" data-iam-screen="pending-approvals" data-empty-message="No pending registrations loaded yet. Approval rows will appear here after the queue is connected.">
    <div class="card-header">
        <div>
            <h3 class="card-title mb-1">Pending Registrations</h3>
            <div class="text-secondary">Approval queue for self-registered users that still require role selection and activation.</div>
        </div>
    </div>
    <div class="card-body border-bottom">
        <div id="iam-pending-approvals-feedback" class="d-none mb-3" data-form-feedback></div>
        <form class="row g-2 align-items-end" data-iam-filters>
            <div class="col-12 col-md-6">
                <label class="form-label" for="iam-pending-search">Search applicants</label>
                <input type="search" class="form-control" id="iam-pending-search" name="search" placeholder="Name, email, or organization" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="iam-pending-age">Submitted</label>
                <select class="form-select" id="iam-pending-age" name="submitted">
                    <option value="">Any time</option>
                    <option value="today">Today</option>
                    <option value="week">This week</option>
                    <option value="month">This month</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-grid">
                <button type="button" class="btn btn-outline-secondary" data-iam-reset>Reset</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Contact</th>
                    <th>Assign Role</th>
                    <th>Submitted</th>
                    <th class="text-end">Decision</th>
                </tr>
            </thead>
            <tbody id="iam-pending-body" data-iam-table-body data-empty-colspan="5">
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        No pending registrations loaded yet. Approval rows and decisions appear here after binding.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary">Pagination will appear after the queue is connected.</p>
        <ul class="pagination m-0 ms-auto" id="iam-pending-pagination" data-iam-pagination></ul>
    </div>
</div>
