<?php

declare(strict_types=1);

/** @var list<\WorkEddy\Modules\Content\Domain\ContentMedia> $mediaItems */

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Content Media';
$pagePurpose = 'Platform';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Content', 'url' => '/content'],
    ['label' => 'Media', 'url' => null],
];
$pageActions = [
    [
        'label' => 'Open Content',
        'url' => '/content',
        'class' => 'btn btn-outline-secondary',
        'icon' => 'file-earmark-richtext',
    ],
];
$pageScripts = ['js/modules/content-media.js'];

require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="py-4" id="contentMediaPage">
    <div class="card" id="contentMediaCard" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <h5 class="card-title mb-1">Media Library</h5>
                <p class="text-muted mb-0">Storage-backed image assets available for editorial image blocks.</p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 align-items-stretch">
                <input class="form-control" id="contentMediaSearch" type="search" placeholder="Search media by name, UUID, or storage file">
                <select class="form-select" id="contentMediaMimeFilter">
                    <option value="">All MIME types</option>
                    <option value="image/jpeg">JPEG</option>
                    <option value="image/png">PNG</option>
                    <option value="image/webp">WebP</option>
                </select>
                <span class="badge bg-label-primary d-inline-flex align-items-center justify-content-center px-3" id="contentMediaCountBadge">0</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="contentMediaTable">
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>MIME</th>
                        <th>Dimensions</th>
                        <th>Storage file</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="contentMediaBody">
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></div>
                            Loading media library...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted small" id="contentMedia-result-count">0 total records.</div>
    </div>
</div>