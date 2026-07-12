<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Recommendation Review';
$pagePurpose = 'Validate generated controls before they become assigned work.';
$breadcrumbs = [
    ['label' => 'Corrective Actions', 'url' => '/corrective-actions'],
    ['label' => 'Recommendations', 'url' => null],
];
$pageActions = [
    ['label' => 'Action register', 'url' => '/corrective-actions', 'class' => 'btn btn-outline-secondary', 'icon' => 'kanban'],
];
$pageScripts = ['js/modules/corrective-action.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="caRecommendationsPage" data-ca-page="recommendations">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-4 align-items-end">
                <div class="col-lg-7">
                    <label for="caAssessmentSelect" class="form-label">Reviewed assessment</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-clipboard-pulse"></i></span>
                        <select id="caAssessmentSelect" class="form-select"></select>
                        <button id="caLoadRecommendations" class="btn btn-outline-secondary cursor-pointer" type="button">Load</button>
                    </div>
                    <div class="form-text">Select an assessment to review its generated controls.</div>
                </div>
                <div class="col-lg-5 d-flex flex-column flex-sm-row gap-2">
                    <button id="caGenerateRecommendations" class="btn btn-primary cursor-pointer flex-fill" type="button">
                        <i class="bi bi-stars me-1"></i>Generate recommendations
                    </button>
                    <button id="caRefreshRecommendations" class="btn btn-outline-secondary cursor-pointer" type="button">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h5 class="card-title mb-1">Control recommendations</h5>
                <p class="text-muted small mb-0">Prioritized by hierarchy of controls and expected risk reduction.</p>
            </div>
            <span class="badge bg-label-primary" id="caRecommendationCount">0 recommendations</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Recommendation</th>
                        <th>Hierarchy</th>
                        <th>Reduction</th>
                        <th>Status</th>
                        <th>Timing</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="caRecommendationsBody">
                    <tr><td colspan="6" class="text-muted">Loading recommendations...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="caAssignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="caAssignForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Assign corrective action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="caAssignRecommendationUuid">
                    <div class="mb-3">
                        <label class="form-label">Accepted recommendation</label>
                        <div class="rounded border bg-body-tertiary px-3 py-2 small" id="caAssignRecommendationSummary">Select a recommendation first.</div>
                    </div>
                    <div class="mb-3">
                        <label for="caAssignedToUserUuid" class="form-label">Responsible person</label>
                        <select id="caAssignedToUserUuid" name="assignedToUserUuid" class="form-select" required></select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="caAssignDueDate" class="form-label">Due date</label>
                            <input id="caAssignDueDate" name="dueDate" class="form-control" type="date">
                        </div>
                        <div class="col-md-6">
                            <label for="caAssignFollowUpDate" class="form-label">Follow-up assessment date</label>
                            <input id="caAssignFollowUpDate" name="followUpDueDate" class="form-control" type="date">
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Assignment creates the tracked action and keeps original recommendation context.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="caAssignSubmit">
                        <i class="bi bi-send-check me-1"></i>Assign
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="caReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="caReviewForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Accept and refine recommendation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="caReviewRecommendationUuid">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="caReviewTitle" class="form-label">Action title</label>
                            <input id="caReviewTitle" name="title" class="form-control" type="text" required>
                        </div>
                        <div class="col-md-4">
                            <label for="caReviewPriority" class="form-label">Priority</label>
                            <select id="caReviewPriority" name="priority" class="form-select">
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="caReviewDescription" class="form-label">Recommended change</label>
                            <textarea id="caReviewDescription" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="caReviewReason" class="form-label">Why this action is needed</label>
                            <textarea id="caReviewReason" name="reason" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="caReviewDueDays" class="form-label">Target due days</label>
                            <input id="caReviewDueDays" name="dueDays" class="form-control" type="number" min="1">
                        </div>
                        <div class="col-md-4">
                            <label for="caReviewFollowUpDays" class="form-label">Follow-up days after due date</label>
                            <input id="caReviewFollowUpDays" name="followUpDays" class="form-control" type="number" min="1">
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check form-switch mt-4">
                                <input id="caReviewEvidenceRequired" name="evidenceRequired" class="form-check-input" type="checkbox">
                                <label for="caReviewEvidenceRequired" class="form-check-label">Evidence required</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Required evidence</label>
                            <div class="row g-2" id="caReviewEvidenceTypes">
                                <?php foreach (['photo' => 'Photo', 'video' => 'Video', 'receipt' => 'Receipt', 'note' => 'Field note', 'worker_feedback' => 'Worker feedback', 'follow_up_observation' => 'Follow-up observation', 'document' => 'Document'] as $value => $label): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="caReviewEvidenceType_<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" name="evidenceTypes[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                            <label class="form-check-label" for="caReviewEvidenceType_<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="caReviewSubmit">
                        <i class="bi bi-check2-circle me-1"></i>Accept recommendation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="caRejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="caRejectForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Reject recommendation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="caRejectRecommendationUuid">
                    <div class="mb-3">
                        <label class="form-label">Recommendation</label>
                        <div class="rounded border bg-body-tertiary px-3 py-2 small" id="caRejectRecommendationSummary">Select a recommendation first.</div>
                    </div>
                    <div class="mb-0">
                        <label for="caRejectReason" class="form-label">Reason for rejection</label>
                        <textarea id="caRejectReason" name="reason" class="form-control" rows="4" placeholder="Explain why this control should not move forward." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="caRejectSubmit">
                        <i class="bi bi-x-circle me-1"></i>Reject recommendation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>