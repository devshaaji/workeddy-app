<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Settings';
$pagePurpose = 'Audit';
$pageScripts = ['js/logs.js'];
$can = is_array($can ?? null) ? $can : [];
$disabled = empty($can['manageSettings']) ? ' disabled' : '';
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div id="audit-settings-feedback" class="alert alert-primary d-none mb-4" role="alert"></div>

<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0">Retention &amp; Query Policy</h5>
        <div class="btn-group btn-group-sm" role="group" aria-label="Audit settings actions">
            <button type="button" class="btn btn-outline-secondary" id="audit-settings-reset" data-action="audit-settings-reset" <?= $disabled ?>>Reset Defaults</button>
            <button type="submit" class="btn btn-primary" id="audit-settings-save" form="audit-settings-form" data-action="audit-settings-save" <?= $disabled ?>>Save Settings</button>
        </div>
    </div>
    <div class="card-body">
        <form id="audit-settings-form" data-endpoint="/api/v1/audit/settings" autocomplete="off">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="audit-settings-retention-days">Retention period</label>
                    <div class="input-group">
                        <input type="number" id="audit-settings-retention-days" name="retention_days" class="form-control" min="30" max="3650" placeholder="Retention days" <?= $disabled ?>>
                        <span class="input-group-text">days</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="audit-settings-max-query-results">Max query results</label>
                    <input type="number" id="audit-settings-max-query-results" name="max_query_results" class="form-control" min="10" max="10000" placeholder="Max results" <?= $disabled ?>>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Capture Rules</h5>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="audit-settings-mask-sensitive-fields" name="mask_sensitive_fields" value="1" form="audit-settings-form" <?= $disabled ?>>
                    <label class="form-check-label" for="audit-settings-mask-sensitive-fields">Mask sensitive fields</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="audit-settings-record-ip-address" name="record_ip_address" value="1" form="audit-settings-form" <?= $disabled ?>>
                    <label class="form-check-label" for="audit-settings-record-ip-address">Record IP address</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="audit-settings-store-state-diffs" name="store_state_diffs" value="1" form="audit-settings-form" <?= $disabled ?>>
                    <label class="form-check-label" for="audit-settings-store-state-diffs">Store state diffs</label>
                </div>
            </div>
        </div>
    </div>
</div>
