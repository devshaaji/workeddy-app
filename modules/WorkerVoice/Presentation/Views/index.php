<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Worker Feedback Register';
$pagePurpose = 'Worker voice and discomfort reporting';
$pageActions = [
    ['label' => 'Submit feedback', 'url' => '/worker-voice/new', 'class' => 'btn btn-primary', 'icon' => 'chat-square-text'],
    ['label' => 'Trends', 'url' => '/worker-voice/trends', 'class' => 'btn btn-outline-secondary', 'icon' => 'bar-chart'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Worker Voice', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="workerVoiceIndexPage">
    <div id="workerVoiceIndexAlert"></div>
    <div class="card" id="workerVoiceTableCard" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group input-group-merge" style="min-width: 280px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" id="workerVoiceSearchFilter" class="form-control" placeholder="Search region, task, assessment or note">
                    </div>

                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="workerVoiceBodyRegionFilter" class="form-label">Body region</label>
                    <select id="workerVoiceBodyRegionFilter" class="form-select">
                        <option value="">All body regions</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="workerVoiceAnonymousFilter" class="form-label">Anonymous mode</label>
                    <select id="workerVoiceAnonymousFilter" class="form-select">
                        <option value="">All</option>
                        <option value="1">Anonymous only</option>
                        <option value="0">Identified only</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="workerVoiceDateFrom" class="form-label">From</label>
                    <input type="date" id="workerVoiceDateFrom" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="workerVoiceDateTo" class="form-label">To</label>
                    <input type="date" id="workerVoiceDateTo" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Refresh</label>
                    <button type="button" class="form-control btn btn-outline-secondary" id="workerVoiceRefreshBtn">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 72px;">#</th>
                        <th>Region</th>
                        <th>Task</th>
                        <th>Discomfort</th>
                        <th>Pain window</th>
                        <th>Anonymous</th>
                        <th>Created</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="workerVoiceIndexTable">
                    <tr>
                        <td colspan="8" class="text-muted">Loading feedback...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="text-muted small" id="workerVoiceResultCount">Loading records...</div>
            <ul class="pagination m-0 ms-md-auto" id="workerVoicePagination"></ul>
        </div>
    </div>
</div>