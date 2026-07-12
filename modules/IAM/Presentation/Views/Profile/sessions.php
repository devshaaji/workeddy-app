<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'My Sessions';
$pagePurpose = 'Review active and recent sessions for your account.';
$pageScripts = ['js/iam.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
$profileTab = 'sessions';
require __DIR__ . '/_tabs.php';
?>

<div class="card" data-iam-screen="profile-sessions" data-empty-message="No sessions loaded yet. Session rows will appear here after binding.">
    <div class="card-header">
        <h3 class="card-title mb-0">Session History</h3>
    </div>
    <div class="card-body border-bottom">
        <div id="iam-profile-sessions-feedback" class="d-none mb-3" data-form-feedback></div>
        <form class="row g-2 align-items-end" data-iam-filters>
            <div class="col-12 col-md-5">
                <label class="form-label" for="iam-profile-sessions-search">Search sessions</label>
                <input type="search" class="form-control" id="iam-profile-sessions-search" name="search" placeholder="Device, IP address, or location" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="iam-profile-sessions-status">Status</label>
                <select class="form-select" id="iam-profile-sessions-status" name="status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="iam-profile-sessions-age">Started</label>
                <select class="form-select" id="iam-profile-sessions-age" name="started">
                    <option value="">Any time</option>
                    <option value="today">Today</option>
                    <option value="week">This week</option>
                    <option value="month">This month</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="button" class="btn btn-outline-secondary" data-iam-reset>Reset</button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>IP Address</th>
                        <th>Location</th>
                        <th>Started</th>
                        <th>Last Seen</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="iam-profile-sessions-body" data-iam-table-body data-empty-colspan="7">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            Session rows appear here when available.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ul class="pagination m-0 justify-content-end" id="iam-profile-sessions-pagination" data-iam-pagination></ul>
    </div>
</div>
