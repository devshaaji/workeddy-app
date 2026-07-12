<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Research Export';
$pagePurpose = 'De-identified evidence delivery';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Research Export', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$summary = is_array($summary ?? null) ? $summary : [];
?>

<div class="flex-grow-1 py-4" id="exportPage">
    <div id="researchExportAlert"></div>

    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-widget-separator-wrapper">
                <div class="card-body card-widget-separator">
                    <div class="row gy-4 gy-sm-1">
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-1 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0"><?= (int) ($summary['recentExportCount'] ?? 0) ?></h4>
                                    <p class="mb-0">Recent exports</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-primary text-heading">
                                        <i class="bi bi-folder2-open"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none me-6">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-2 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0"><?= (int) ($summary['readyExportCount'] ?? 0) ?></h4>
                                    <p class="mb-0">Ready exports</p>
                                </div>
                                <div class="avatar me-lg-6">
                                    <span class="avatar-initial rounded bg-label-success text-heading">
                                        <i class="bi bi-check2-circle"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0 card-widget-3">
                                <div>
                                    <h4 class="mb-0"><?= number_format((int) ($summary['totalRows'] ?? 0)) ?></h4>
                                    <p class="mb-0">Exported rows</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-warning text-heading">
                                        <i class="bi bi-bar-chart-line"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0"><?= (int) ($summary['signedLinkTtlMinutes'] ?? 0) ?> min</h4>
                                    <p class="mb-0">Signed link TTL</p>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-info text-heading">
                                        <i class="bi bi-clock-history"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">Prepare export</h5>
                <p class="text-muted small mb-0">Choose the dataset, output format, and date range before preview or generation.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-label-primary"><?= count($datasets ?? []) ?> datasets</span>
                <span class="badge bg-label-secondary"><?= htmlspecialchars(strtoupper(implode(' / ', $allowedFormats ?? []))) ?></span>
            </div>
        </div>
        <div class="card-body">
            <form id="research-export-form" class="row g-3" novalidate>
                <div class="col-md-6">
                    <label for="researchExportDataset" class="form-label">Dataset</label>
                    <select id="researchExportDataset" name="dataset" class="form-select">
                        <?php foreach (($datasets ?? []) as $dataset): ?>
                            <option value="<?= htmlspecialchars((string) $dataset) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $dataset))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="researchExportFormat" class="form-label">Format</label>
                    <select id="researchExportFormat" name="format" class="form-select">
                        <?php foreach (($allowedFormats ?? []) as $format): ?>
                            <option value="<?= htmlspecialchars((string) $format) ?>" <?= ($defaultFormat ?? 'csv') === $format ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper((string) $format)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="researchExportFromDate" class="form-label">From date</label>
                    <input id="researchExportFromDate" type="date" name="fromDate" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="researchExportToDate" class="form-label">To date</label>
                    <input id="researchExportToDate" type="date" name="toDate" class="form-control">
                </div>
                <div class="col-12 d-flex flex-wrap gap-2 justify-content-end pt-2">
                    <button type="button" class="btn btn-outline-primary" id="research-export-preview">
                        <i class="bi bi-search me-1"></i>Preview fields
                    </button>
                    <button type="button" class="btn btn-primary" id="research-export-generate">
                        <i class="bi bi-box-arrow-down-right me-1"></i>Generate export
                    </button>
                </div>
            </form>
        </div>
    </div>

    <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">Recent exports</h5>
                <p class="text-muted small mb-0">Re-issue signed access for ready files without regenerating the dataset.</p>
            </div>
            <span class="badge bg-label-primary"><?= count($recentExports ?? []) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Dataset</th>
                        <th>Format</th>
                        <th>Rows</th>
                        <th>Generated at</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="researchExportRecentBody">
                    <?php foreach (($recentExports ?? []) as $item): ?>
                        <?php
                        $status = (string) ($item['status'] ?? 'pending');
                        $statusClass = match ($status) {
                            'ready' => 'success',
                            'failed' => 'danger',
                            'processing' => 'warning',
                            default => 'secondary',
                        };
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($item['dataset'] ?? 'assessments')))) ?></td>
                            <td><?= htmlspecialchars(strtoupper((string) ($item['format'] ?? 'csv'))) ?></td>
                            <td><?= number_format((int) ($item['rowCount'] ?? 0)) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars((string) ($item['generatedAt'] ?? 'Pending generation')) ?></td>
                            <td><span class="badge bg-label-<?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td class="text-end">
                                <button type="button"
                                    class="btn btn-sm btn-outline-primary research-export-download"
                                    data-export-uuid="<?= htmlspecialchars((string) ($item['uuid'] ?? '')) ?>"
                                    <?= $status !== 'ready' ? ' disabled' : '' ?>>
                                    <i class="bi bi-link-45deg me-1"></i>Signed link
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($recentExports ?? []) === []): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No exports yet. Run a preview, then generate the first research file.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="researchExportPreviewModal" tabindex="-1" aria-labelledby="researchExportPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="researchExportPreviewModalLabel">Preview fields</h5>
                    <p class="text-muted small mb-0">Inspect included columns, excluded fields, and transformations before export generation.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="research-export-preview-empty" class="text-muted small">No preview loaded yet.</div>
                <div id="research-export-preview-panel" class="d-none">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="card bg-lighter shadow-none border h-100">
                                <div class="card-body">
                                    <div class="small text-muted mb-1">Estimated rows</div>
                                    <div class="fw-bold fs-4" id="researchExportEstimatedRows">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="card bg-lighter shadow-none border h-100">
                                <div class="card-body">
                                    <div class="small text-muted mb-1">Columns included</div>
                                    <div class="fw-bold fs-4" id="researchExportIncludedCount">0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="fw-semibold mb-2">Included columns</div>
                        <div id="researchExportIncludedColumns" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="mb-4">
                        <div class="fw-semibold mb-2">Excluded fields</div>
                        <ul id="researchExportExcludedFields" class="mb-0 ps-3 text-muted small"></ul>
                    </div>
                    <div>
                        <div class="fw-semibold mb-2">Transformations</div>
                        <ul id="researchExportTransformations" class="mb-0 ps-3 text-muted small"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>