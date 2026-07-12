<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Audit Logs';
$pagePurpose = 'Overview';
$pageScripts = ['js/logs.js'];
$pageActions = [
    ['label' => 'Export', 'url' => '/audit/export', 'class' => 'btn btn-primary'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<section
    id="audit-log-screen"
    data-log-screen
    data-api-index="/api/v1/audit/logs"
    data-api-export="/api/v1/audit/logs/export">
    <div id="audit-log-feedback" class="d-none mb-3" data-form-feedback></div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Filters</h3>
            <div class="card-actions">
                <button type="button" class="btn btn-ghost-secondary btn-sm" data-log-reset>Reset</button>
            </div>
        </div>
        <div class="card-body">
            <form id="audit-log-filters" data-log-filters class="row g-3" autocomplete="off">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-actor-id">Actor ID</label>
                    <input type="text" id="audit-log-actor-id" name="actorId" class="form-control" placeholder="User ID">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-module">Module</label>
                    <select id="audit-log-module" name="module" class="form-select">
                        <option value="">All modules</option>
                        <option value="IAM">IAM</option>
                        <option value="Customer">Customer</option>
                        <option value="Notification">Notification</option>
                        <option value="Audit">Audit</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-action">Action</label>
                    <input type="text" id="audit-log-action" name="action" class="form-control" placeholder="Action name">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-entity-type">Entity Type</label>
                    <input type="text" id="audit-log-entity-type" name="entityType" class="form-control" placeholder="Entity type">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-entity-id">Entity ID</label>
                    <input type="text" id="audit-log-entity-id" name="entityId" class="form-control" placeholder="Entity identifier">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-from-date">From</label>
                    <input type="datetime-local" id="audit-log-from-date" name="fromDate" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-to-date">To</label>
                    <input type="datetime-local" id="audit-log-to-date" name="toDate" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="audit-log-limit">Result Limit</label>
                    <input type="number" id="audit-log-limit" name="limit" class="form-control" min="1" max="10000" placeholder="100">
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Audit Events</h3>
            <div class="card-actions">
                <button type="button" class="btn btn-outline-primary btn-sm" id="audit-log-refresh">Refresh</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th><button class="table-sort d-flex justify-content-between" data-sort="createdAt">Recorded</button></th>
                        <th><button class="table-sort d-flex justify-content-between" data-sort="actorLabel">Actor</button></th>
                        <th><button class="table-sort d-flex justify-content-between" data-sort="module">Module</button></th>
                        <th><button class="table-sort d-flex justify-content-between" data-sort="action">Action</button></th>
                        <th><button class="table-sort d-flex justify-content-between" data-sort="entityType">Entity</button></th>
                        <th>IP Address</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody id="audit-log-table-body" data-log-table-body>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">Audit events will appear here.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center">
            <p class="m-0 text-secondary" id="audit-log-result-count">Audit events load from the Audit API.</p>
            <ul class="pagination m-0 ms-auto" id="audit-log-pagination"></ul>
        </div>
    </div>
</section>

<div class="modal modal-blur fade" id="log-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Event Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="log-detail-json" class="admin-json-preview bg-dark text-white rounded p-3 mb-0">{}</pre>
            </div>
        </div>
    </div>
</div>
