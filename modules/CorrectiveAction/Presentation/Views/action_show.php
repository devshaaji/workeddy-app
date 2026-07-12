<?php

declare(strict_types=1);

$actionId = (string) (($routeParams['actionId'] ?? '') ?: '');
$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Corrective Action Detail';
$pagePurpose = 'Review owner, evidence, status history, and verification readiness.';
$breadcrumbs = [
    ['label' => 'Corrective Actions', 'url' => '/corrective-actions'],
    ['label' => 'Detail', 'url' => null],
];
$pageActions = [
    ['label' => 'Upload evidence', 'url' => '/corrective-actions/' . $actionId . '/evidence', 'class' => 'btn btn-primary', 'icon' => 'cloud-upload'],
    ['label' => 'Back to register', 'url' => '/corrective-actions', 'class' => 'btn btn-outline-secondary', 'icon' => 'arrow-left'],
];
$pageScripts = ['js/modules/corrective-action.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" data-ca-page="show" data-action-id="<?= htmlspecialchars($actionId, ENT_QUOTES, 'UTF-8') ?>">
    <section class="row g-4">
        <div class="col-xl-8">
            <article class="card mb-4" style="border-radius: var(--we-radius-xl); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <span class="badge bg-label-primary mb-3" id="caDetailPriority">Loading</span>
                            <h3 class="fw-bold mb-2" id="caDetailTitle">Loading corrective action</h3>
                            <p class="text-muted mb-0" id="caDetailDescription">Fetching current state and ownership.</p>
                        </div>
                        <div class="text-md-end">
                            <div id="caDetailStatus" class="mb-2"></div>
                            <small class="text-muted d-block">Due <span id="caDetailDue">-</span></small>
                            <small class="text-muted d-block">Follow-up <span id="caDetailFollowUp">-</span></small>
                        </div>
                    </div>
                </div>
            </article>

            <article class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h5 class="mb-0">Workflow controls</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="caNextStatus" class="form-label">Move to status</label>
                            <select id="caNextStatus" class="form-select">
                                <option value="in_progress">In progress</option>
                                <option value="completed">Completed</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label for="caStatusNotes" class="form-label">Notes</label>
                            <input id="caStatusNotes" class="form-control" type="text" placeholder="Reason, blocker, or implementation note">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-primary cursor-pointer" type="button" id="caUpdateStatus">
                            <i class="bi bi-arrow-repeat me-1"></i>Update status
                        </button>
                        <button class="btn btn-success cursor-pointer" type="button" id="caVerifyAction">
                            <i class="bi bi-shield-check me-1"></i>Verify and schedule follow-up
                        </button>
                        <span id="caComparisonLinkWrap"></span>
                    </div>
                </div>
            </article>

            <article class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h5 class="mb-0">Follow-up assessment</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-7">
                            <label for="caFollowUpDueDate" class="form-label">Follow-up due date</label>
                            <input id="caFollowUpDueDate" class="form-control" type="date">
                            <div class="form-text">Use this to request or reschedule the comparison assessment date.</div>
                        </div>
                        <div class="col-md-5 d-grid">
                            <button class="btn btn-outline-primary cursor-pointer" type="button" id="caScheduleFollowUp">
                                <i class="bi bi-calendar-check me-1"></i>Save follow-up date
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <article class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Evidence</h5>
                    <a class="btn btn-sm btn-outline-primary" href="/corrective-actions/<?= htmlspecialchars($actionId, ENT_QUOTES, 'UTF-8') ?>/evidence">
                        <i class="bi bi-plus-lg me-1"></i>Add evidence
                    </a>
                </div>
                <div class="card-body" id="caEvidenceList"></div>
            </article>
        </div>

        <aside class="col-xl-4">
            <article class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h5 class="mb-0">Control context</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Assessment</dt>
                        <dd class="col-7 text-break" id="caDetailAssessment">-</dd>
                        <dt class="col-5 text-muted">Hierarchy</dt>
                        <dd class="col-7" id="caDetailHierarchy">-</dd>
                        <dt class="col-5 text-muted">Control type</dt>
                        <dd class="col-7" id="caDetailControlType">-</dd>
                        <dt class="col-5 text-muted">Owner</dt>
                        <dd class="col-7" id="caDetailOwner">-</dd>
                        <dt class="col-5 text-muted">Reason</dt>
                        <dd class="col-7" id="caDetailReason">-</dd>
                        <dt class="col-5 text-muted">Evidence needed</dt>
                        <dd class="col-7" id="caDetailEvidenceRequirements">-</dd>
                        <dt class="col-5 text-muted">Reject reason</dt>
                        <dd class="col-7" id="caDetailRejectReason">-</dd>
                    </dl>
                </div>
            </article>

            <article class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h5 class="mb-0">Status history</h5>
                </div>
                <div class="card-body" id="caHistoryList"></div>
            </article>
        </aside>
    </section>
</div>