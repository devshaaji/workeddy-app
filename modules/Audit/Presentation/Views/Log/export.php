<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Export';
$pagePurpose = 'Audit';
$pageScripts = ['js/logs.js'];
$can = is_array($can ?? null) ? $can : [];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<section
    id="audit-export-screen"
    data-api-export="/api/v1/audit/logs/export"
    data-default-format="csv">
    <div id="audit-export-feedback" class="alert alert-primary d-none mb-4" role="alert"></div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h5 class="mb-0">Export Criteria</h5>
            <button type="button" class="btn btn-primary btn-sm" id="audit-export-prepare">Prepare Export</button>
        </div>
        <div class="card-body">
            <form id="audit-export-filters" class="row g-4" autocomplete="off">
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-actor-id">Actor ID</label>
                    <input type="text" id="audit-export-actor-id" name="actorId" class="form-control" placeholder="User ID">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-module">Module</label>
                    <select id="audit-export-module" name="module" class="form-select">
                        <option value="">All modules</option>
                        <option value="IAM">IAM</option>
                        <option value="Client">Client</option>
                        <option value="Gazette">Gazette</option>
                        <option value="Invoice">Invoice</option>
                        <option value="Payment">Payment</option>
                        <option value="Notification">Notification</option>
                        <option value="Audit">Audit</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-action">Action</label>
                    <input type="text" id="audit-export-action" name="action" class="form-control" placeholder="Action name">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-entity-type">Entity Type</label>
                    <input type="text" id="audit-export-entity-type" name="entityType" class="form-control" placeholder="Entity type">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-entity-id">Entity ID</label>
                    <input type="text" id="audit-export-entity-id" name="entityId" class="form-control" placeholder="Entity identifier">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-from-date">From</label>
                    <input type="datetime-local" id="audit-export-from-date" name="fromDate" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-to-date">To</label>
                    <input type="datetime-local" id="audit-export-to-date" name="toDate" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-limit">Result Limit</label>
                    <input type="number" id="audit-export-limit" name="limit" class="form-control" min="1" max="10000" placeholder="Limit">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="audit-export-format">Format</label>
                    <select id="audit-export-format" name="format" class="form-select">
                        <option value="csv" selected>CSV</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
</section>
