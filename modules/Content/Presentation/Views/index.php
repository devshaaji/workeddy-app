<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Content Pages';
$pagePurpose = 'Platform';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Content', 'url' => null],
];
$pageActions = [
    [
        'label' => 'Create Page',
        'url' => '/content/create',
        'class' => 'btn btn-primary',
        'icon' => 'plus-lg',
    ],
];
$pageScripts = ['js/modules/content-pages.js'];

require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="contentPagesIndex">
    <div class="card" id="contentPagesCard" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header">
            <div class="row w-full g-2 align-items-center">
                <div class="col-md-auto">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group w-auto">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input id="content-pages-search" type="search" class="form-control" placeholder="Search pages…">
                        </div>
                        <select id="content-pages-audience" class="form-select w-auto">
                            <option value="">All audiences</option>
                            <option value="internal">Internal</option>
                            <option value="public">Public</option>
                        </select>
                        <select id="content-pages-status" class="form-select w-auto">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="w-1">#</th>
                        <th><button type="button" class="table-sort" data-sort="title">Page <i class="bi bi-chevron-expand ms-1 text-muted opacity-50"></i></button></th>
                        <th><button type="button" class="table-sort" data-sort="audience">Audience <i class="bi bi-chevron-expand ms-1 text-muted opacity-50"></i></button></th>
                        <th><button type="button" class="table-sort" data-sort="status">Status <i class="bi bi-chevron-expand ms-1 text-muted opacity-50"></i></button></th>
                        <th>Template</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="contentPagesBody">
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></div>
                            Loading content pages...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center">
            <p class="m-0 text-secondary" id="contentPages-result-count">0 total records.</p>
            <ul class="pagination m-0 ms-auto" id="contentPages-pagination"></ul>
        </div>
    </div>
</div>