<?php

declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$can = is_array($can ?? null) ? $can : [];
$canUpload = (bool) ($can['upload'] ?? false);
$canDelete = (bool) ($can['delete'] ?? false);
$canManageSettings = (bool) ($can['manageSettings'] ?? false);

$pageActions = [];
if ($canUpload) {
    $pageActions[] = ['label' => 'Upload Files', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'cloud-upload', 'id' => 'sfmUploadBtn'];
}
if ($canManageSettings) {
    $pageActions[] = ['label' => 'Storage Settings', 'url' => '/settings/page?module=storage', 'class' => 'btn btn-outline-secondary', 'icon' => 'gear'];
}
require $v2Root . '/shared/Views/Partials/page_header.php';
?>
<div id="sfmApp"
    data-can-upload="<?= $canUpload ? '1' : '0' ?>"
    data-can-delete="<?= $canDelete ? '1' : '0' ?>"
    data-can-manage-settings="<?= $canManageSettings ? '1' : '0' ?>">

    <!-- Storage Summary -->
    <div class="row g-3 mb-4" id="sfmSummaryRow">
        <div class="col-6 col-xl-3">
            <div class="card sfm-stat-card h-100">
                <div class="card-body d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Total Files</span>
                        <h3 class="mb-0 fw-bold" data-sfm-stat="totalFiles">—</h3>
                    </div>
                    <div class="sfm-stat-icon bg-primary-subtle text-primary"><i class="bi bi-files"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card sfm-stat-card h-100">
                <div class="card-body d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Storage Used</span>
                        <h3 class="mb-0 fw-bold" data-sfm-stat="totalUsed">—</h3>
                    </div>
                    <div class="sfm-stat-icon bg-info-subtle text-info"><i class="bi bi-hdd"></i></div>
                </div>
                <div class="card-footer py-2 px-3 bg-transparent border-0" id="sfmQuotaWrap" style="display:none">
                    <div class="progress" style="height:6px">
                        <div class="progress-bar" id="sfmQuotaBar" role="progressbar" style="width:0%"></div>
                    </div>
                    <span class="small text-muted" id="sfmQuotaText"></span>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card sfm-stat-card h-100">
                <div class="card-body d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Images</span>
                        <h3 class="mb-0 fw-bold" data-sfm-stat="images">—</h3>
                    </div>
                    <div class="sfm-stat-icon bg-success-subtle text-success"><i class="bi bi-image"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card sfm-stat-card h-100">
                <div class="card-body d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Documents</span>
                        <h3 class="mb-0 fw-bold" data-sfm-stat="documents">—</h3>
                    </div>
                    <div class="sfm-stat-icon bg-warning-subtle text-warning"><i class="bi bi-file-earmark-text"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Navigation -->
        <div class="col-lg-3 col-xxl-2">
            <div class="card sfm-nav-card mb-3">
                <div class="list-group list-group-flush" id="sfmNav">
                    <button type="button" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between" data-sfm-filter="type" data-sfm-value="">
                        <span><i class="bi bi-collection me-2"></i>All Files</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-sfm-filter="type" data-sfm-value="image">
                        <span><i class="bi bi-image me-2"></i>Images</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-sfm-filter="type" data-sfm-value="document">
                        <span><i class="bi bi-file-earmark-text me-2"></i>Documents</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-sfm-filter="type" data-sfm-value="video">
                        <span><i class="bi bi-camera-video me-2"></i>Videos</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-sfm-filter="type" data-sfm-value="audio">
                        <span><i class="bi bi-music-note-beamed me-2"></i>Audio</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-sfm-filter="type" data-sfm-value="archive">
                        <span><i class="bi bi-file-earmark-zip me-2"></i>Archives</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-sfm-filter="sort" data-sfm-value="newest">
                        <span><i class="bi bi-clock-history me-2"></i>Recent</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-sfm-filter="visibility" data-sfm-value="public">
                        <span><i class="bi bi-globe2 me-2"></i>Public</span>
                    </button>
                    <?php if ($canDelete): ?>
                        <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between text-danger" data-sfm-filter="trash" data-sfm-value="1">
                            <span><i class="bi bi-trash3 me-2"></i>Trash</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9 col-xxl-10">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2 border-bottom">
                    <h5 class="card-title mb-0 me-2" id="sfmListTitle">All Files</h5>

                    <div class="input-group input-group-sm sfm-search-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0" id="sfmSearch" placeholder="Search by file name…" aria-label="Search files">
                    </div>

                    <select class="form-select form-select-sm sfm-select" id="sfmSort" aria-label="Sort files">
                        <option value="date:desc">Newest first</option>
                        <option value="date:asc">Oldest first</option>
                        <option value="name:asc">Name (A–Z)</option>
                        <option value="name:desc">Name (Z–A)</option>
                        <option value="size:desc">Largest first</option>
                        <option value="size:asc">Smallest first</option>
                    </select>

                    <div class="ms-auto d-flex align-items-center gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="View mode">
                            <button type="button" class="btn btn-outline-secondary active" id="sfmViewGrid" title="Grid view"><i class="bi bi-grid-3x3-gap"></i></button>
                            <button type="button" class="btn btn-outline-secondary" id="sfmViewTable" title="Table view"><i class="bi bi-list-ul"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Body: grid / table / loading / empty / error render here -->
                <div class="card-body" id="sfmListBody">
                    <div class="sfm-loading py-5 text-center text-muted">
                        <span class="spinner-border spinner-border-sm me-2"></span>Loading files…
                    </div>
                </div>

                <div class="card-footer d-flex align-items-center gap-3 flex-wrap">
                    <span class="text-muted small" id="sfmPageInfo"></span>
                    <nav class="ms-auto" aria-label="File list pagination">
                        <ul class="pagination pagination-sm mb-0" id="sfmPagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="sfmUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Upload Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sfmUploadAlert" class="mb-3"></div>

                <div class="sfm-dropzone" id="sfmDropzone" tabindex="0" role="button"
                    aria-label="Drag and drop files here, or click to browse">
                    <input type="file" id="sfmFileInput" multiple class="visually-hidden">
                    <i class="bi bi-cloud-arrow-up display-6 text-primary"></i>
                    <p class="fw-medium mb-1">Drag &amp; drop files here</p>
                    <p class="text-muted small mb-2">or click to browse from your device</p>
                    <p class="text-muted small mb-0" id="sfmUploadConstraints">Loading upload limits…</p>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label small fw-medium">Visibility</label>
                    <select class="form-select form-select-sm" id="sfmUploadVisibility" style="max-width:220px">
                        <option value="private" selected>Private (admin only)</option>
                        <option value="public">Public (shareable link)</option>
                    </select>
                </div>

                <div id="sfmUploadList" class="sfm-upload-list"></div>
            </div>
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="sfmUploadTotalProgressLabel"></span>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sfmUploadStartBtn" disabled>
                    <i class="bi bi-upload me-1"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="sfmPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-truncate" id="sfmPreviewTitle">Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" id="sfmPreviewBody">
                <div class="py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading preview…</div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="sfmPreviewDetailsBtn">
                    <i class="bi bi-info-circle me-1"></i>View details
                </button>
                <a href="#" class="btn btn-primary btn-sm" id="sfmPreviewDownloadBtn" target="_blank" rel="noopener">
                    <i class="bi bi-download me-1"></i>Download
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Details Offcanvas -->
<div class="offcanvas offcanvas-end sfm-details-offcanvas" tabindex="-1" id="sfmDetailsPanel" aria-labelledby="sfmDetailsPanelLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="sfmDetailsPanelLabel">File Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="sfmDetailsBody">
        <div class="py-5 text-center text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
    </div>
</div>

<!-- Delete / Trash Confirmation Modal -->
<div class="modal fade" id="sfmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-status bg-danger"></div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-exclamation-triangle text-danger display-6 mb-2 d-block"></i>
                <h5 class="mb-1" id="sfmDeleteModalTitle">Move to trash?</h5>
                <p class="text-muted small mb-1" id="sfmDeleteModalFileName"></p>
                <p class="text-muted small" id="sfmDeleteModalWarning">This file can be restored from Trash later.</p>
                <div class="alert alert-warning text-start small d-none" id="sfmDeleteUsageWarning"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger w-100" id="sfmDeleteConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<?php if ($canManageSettings): ?>
    <!-- Storage Settings Modal -->
    <div class="modal fade" id="sfmSettingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gear me-2"></i>Storage Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="sfmSettingsAlert" class="mb-3"></div>
                    <form id="sfmSettingsForm" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-medium" for="sfmSettingDefaultVisibility">Default File Visibility</label>
                            <select class="form-select" id="sfmSettingDefaultVisibility" name="default_visibility">
                                <option value="private">Private</option>
                                <option value="public">Public</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium" for="sfmSettingMaxUpload">Max Upload Size (MB)</label>
                            <input type="number" min="1" max="50" class="form-control" id="sfmSettingMaxUpload" name="max_upload_mb">
                            <div class="form-text">Between 1 and 50 MB.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium" for="sfmSettingExtensions">Allowed Extensions</label>
                            <input type="text" class="form-control" id="sfmSettingExtensions" name="allowed_extensions" placeholder="pdf, doc, docx, jpg, png">
                            <div class="form-text">Comma-separated list.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sfmSettingsSaveBtn">Save Settings</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
