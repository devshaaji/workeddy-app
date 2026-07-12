<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Audit Detail';
$pagePurpose = 'view';
$pageScripts = ['js/logs.js'];
$can = is_array($can ?? null) ? $can : [];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>
<section
    id="audit-log-detail-screen"
    data-api-show-base="/api/v1/audit/logs/"
    data-audit-log-id="<?= htmlspecialchars((string) ($routeParams['id'] ?? '')) ?>"
    data-web-export="/audit/export">

    <div id="audit-log-detail-feedback" class="alert alert-primary d-none mb-4" role="alert"></div>

    <div class="page-header d-flex align-items-center mb-4">
        <div>
            <h2 class="mb-0">Audit Log Detail</h2>
            <small class="text-muted">Event details and state snapshot</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar bg-secondary text-white rounded-circle p-2 small">AL</div>
                        <div>
                            <h5 class="mb-0">Event Metadata</h5>
                            <small class="text-muted">Quick identifiers</small>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="audit-log-detail-export" disabled>Open Export</button>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6 text-muted">Event ID</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-id">—</dd>

                        <dt class="col-6 text-muted">Actor</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-actor-id">—</dd>

                        <dt class="col-6 text-muted">Module</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-module">—</dd>

                        <dt class="col-6 text-muted">Action</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-action">—</dd>

                        <dt class="col-6 text-muted">Entity Type</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-entity-type">—</dd>

                        <dt class="col-6 text-muted">Entity ID</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-entity-id">—</dd>

                        <dt class="col-6 text-muted">IP Address</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-ip-address">—</dd>

                        <dt class="col-6 text-muted">Recorded At</dt>
                        <dd class="col-6 text-end" id="audit-log-detail-created-at">—</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">State Snapshot</h5>
                        <small class="text-muted">Before / After JSON payloads</small>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="audit-log-copy-before">Copy Before</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="audit-log-copy-after">Copy After</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label d-block">Before</label>
                            <pre class="bg-light rounded p-3 mb-0 small" id="audit-log-detail-before">{}</pre>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">After</label>
                            <pre class="bg-light rounded p-3 mb-0 small" id="audit-log-detail-after">{}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
