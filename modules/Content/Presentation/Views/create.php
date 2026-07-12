<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Create Content Page';
$pagePurpose = 'Platform';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Content', 'url' => '/content'],
    ['label' => 'Create Page', 'url' => null],
];
$pageScripts = ['js/modules/content-create.js'];

require $v2Root . '/shared/Views/Partials/page_header.php';

$helpIcon = static function (string $text): string {
    return '<button type="button" class="btn btn-sm btn-icon text-muted p-0 ms-1 align-baseline" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '" aria-label="Help"><i class="bi bi-question-circle"></i></button>';
};
?>

<div class="row justify-content-center" id="contentCreatePage">
    <form id="contentCreateForm" class="card" action="/api/v1/content/pages" method="post" novalidate style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-body">
            <div id="contentCreateForm-feedback" class="mb-3"></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="contentCreatePageKey">Page key<?= $helpIcon('Stable canonical key used by consumers and reporting.') ?></label>
                    <input class="form-control" id="contentCreatePageKey" name="pageKey" type="text" placeholder="pilot-summary" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contentCreateRoutePath">Route path<?= $helpIcon('Internal route path for this managed page.') ?></label>
                    <input class="form-control" id="contentCreateRoutePath" name="routePath" type="text" placeholder="/content/pilot-summary" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="contentCreateTitle">Title</label>
                    <input class="form-control" id="contentCreateTitle" name="title" type="text" placeholder="Pilot Summary" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contentCreateAudience">Audience</label>
                    <select class="form-select" id="contentCreateAudience" name="audience">
                        <option value="internal" selected>Internal</option>
                        <option value="public">Public</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contentCreateTemplateKey">Template</label>
                    <input class="form-control" id="contentCreateTemplateKey" name="templateKey" type="text" value="internal_default" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contentCreateContentType">Content type</label>
                    <input class="form-control" id="contentCreateContentType" name="contentType" type="text" value="structured-page" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="contentCreateChangeSummary">Change summary</label>
                    <input class="form-control" id="contentCreateChangeSummary" name="changeSummary" type="text" value="Page created">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="/content">Cancel</a>
            <button class="btn btn-primary" id="contentCreateSubmitBtn" type="submit">
                <i class="bi bi-plus-lg me-1"></i>Create page
            </button>
        </div>
    </form>

</div>