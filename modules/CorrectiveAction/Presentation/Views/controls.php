<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Corrective Action Controls';
$pagePurpose = 'Search the corrective action catalog and tune recommendation rules in one operator workspace.';
$breadcrumbs = [
    ['label' => 'Corrective Actions', 'url' => '/corrective-actions'],
    ['label' => 'Controls', 'url' => null],
];
$pageActions = [
    ['label' => 'New control', 'url' => '#', 'class' => 'btn btn-warning', 'icon' => 'plus-square', 'id' => 'caNewLibraryItem'],
    ['label' => 'New rule', 'url' => '#', 'class' => 'btn btn-outline-secondary', 'icon' => 'sliders', 'id' => 'caNewRule'],
];
$pageScripts = ['js/modules/corrective-action-controls.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="" data-ca-page="controls">
    <div class="col-12">
        <div class="card">
            <div class="card-widget-separator-wrapper">
                <div class="card-body card-widget-separator">
                    <div class="row gy-4 gy-sm-1">
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-1 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="caSummaryTotalActions">0</h4>
                                    <p class="mb-0">Total controls</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-primary text-heading">
                                        <i class="bi bi-collection"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none me-6">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-2 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="caSummaryActiveActions">0</h4>
                                    <p class="mb-0">Active controls</p>
                                </div>
                                <div class="avatar me-lg-6">
                                    <span class="avatar-initial rounded bg-label-success text-heading">
                                        <i class="bi bi-check-circle"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0 card-widget-3">
                                <div>
                                    <h4 class="mb-0" id="caSummaryActiveRules">0</h4>
                                    <p class="mb-0">Active rules</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-info text-heading">
                                        <i class="bi bi-gear"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0" id="caSummaryRulesReview">0</h4>
                                    <p class="mb-0">Needs review</p>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-warning text-heading">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <div class="nav-align-left">
                <ul class="nav nav-tabs flex-column" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                            data-bs-target="#caLibraryTab" aria-controls="caLibraryTab" aria-selected="true">
                            <i class="bi bi-collection me-2"></i>
                            Controls
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab"
                            data-bs-target="#caRulesTab" aria-controls="caRulesTab" aria-selected="false">
                            <i class="bi bi-gear me-2"></i>
                            Rules
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-0 pt-3 pt-md-0">
                    <!-- ══ Library Tab ══ -->
                    <div class="tab-pane fade show active" id="caLibraryTab" role="tabpanel">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                            <div>
                                <h5 class="mb-1">Control Library</h5>
                                <p class="text-muted small mb-0">Search, inspect, and activate controls available to recommendation logic.</p>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label for="caLibrarySearch" class="form-label">Search controls</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input id="caLibrarySearch" class="form-control" type="search" placeholder="Title, description, risk factor">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label for="caLibraryCategory" class="form-label">Category</label>
                                <select id="caLibraryCategory" class="form-select">
                                    <option value="">All categories</option>
                                    <option value="elimination">Elimination</option>
                                    <option value="substitution">Substitution</option>
                                    <option value="engineering">Engineering</option>
                                    <option value="administrative">Administrative</option>
                                    <option value="ppe">PPE</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label for="caLibraryRisk" class="form-label">Severity / risk</label>
                                <select id="caLibraryRisk" class="form-select">
                                    <option value="">All risk levels</option>
                                    <option value="critical">Critical</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label for="caLibraryStatus" class="form-label">Status</label>
                                <select id="caLibraryStatus" class="form-select">
                                    <option value="">All statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-sm-6 d-grid">
                                <button type="button" class="btn btn-outline-secondary align-self-end" id="caClearLibraryFilters">
                                    <i class="bi bi-x-circle me-1"></i>Clear filters
                                </button>
                            </div>
                        </div>
                        <div class="d-grid gap-3" id="caLibraryList"></div>
                    </div>

                    <!-- ══ Rules Tab ══ -->
                    <div class="tab-pane fade" id="caRulesTab" role="tabpanel">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                            <div>
                                <h5 class="mb-1">Rules</h5>
                                <p class="text-muted small mb-0">Tune which controls surface for specific ergonomic scoring conditions.</p>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-lg-6">
                                <label for="caRuleSearch" class="form-label">Search rules</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input id="caRuleSearch" class="form-control" type="search" placeholder="Action title, risk factor, assessment">
                                </div>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label for="caRuleStatus" class="form-label">Status</label>
                                <select id="caRuleStatus" class="form-select">
                                    <option value="">All statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label for="caRuleAssessmentType" class="form-label">Assessment</label>
                                <select id="caRuleAssessmentType" class="form-select">
                                    <option value="">All types</option>
                                    <option value="reba">REBA</option>
                                    <option value="rula">RULA</option>
                                    <option value="niosh">NIOSH</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label for="caRuleReviewNeeded" class="form-label">Review state</label>
                                <select id="caRuleReviewNeeded" class="form-select">
                                    <option value="">All</option>
                                    <option value="1">Needs review</option>
                                    <option value="0">Healthy</option>
                                </select>
                            </div>
                            <div class="col-md-8 col-lg-5">
                                <label for="caRuleLinkedAction" class="form-label">Linked control</label>
                                <select id="caRuleLinkedAction" class="form-select">
                                    <option value="">All linked controls</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-4 d-grid">
                                <button type="button" class="btn btn-outline-secondary align-self-end" id="caClearRuleFilters">
                                    <i class="bi bi-x-circle me-1"></i>Clear filters
                                </button>
                            </div>
                        </div>
                        <div class="d-grid gap-3" id="caRuleList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="caLibraryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="caLibraryForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="caLibraryModalTitle">New control</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="caLibraryUuid" name="uuid">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="caLibraryTitle" class="form-label">Title</label>
                            <input id="caLibraryTitle" name="title" class="form-control" type="text" required>
                        </div>
                        <div class="col-md-4">
                            <label for="caLibraryPriorityInput" class="form-label">Risk level</label>
                            <select id="caLibraryPriorityInput" name="priority" class="form-select">
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="caLibraryDescription" class="form-label">Description</label>
                            <textarea id="caLibraryDescription" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="caLibraryReason" class="form-label">Why this control is recommended</label>
                            <textarea id="caLibraryReason" name="reason" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="caLibraryHierarchyLevel" class="form-label">Hierarchy of controls</label>
                            <select id="caLibraryHierarchyLevel" name="hierarchyLevel" class="form-select">
                                <option value="elimination">Elimination</option>
                                <option value="substitution">Substitution</option>
                                <option value="engineering">Engineering</option>
                                <option value="administrative">Administrative</option>
                                <option value="ppe">PPE</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="caLibraryControlType" class="form-label">Implementation type</label>
                            <select id="caLibraryControlType" name="controlType" class="form-select">
                                <option value="engineering">Engineering change</option>
                                <option value="workstation_redesign">Workstation redesign</option>
                                <option value="tool_redesign">Tool redesign</option>
                                <option value="lift_assist">Lift assist</option>
                                <option value="administrative">Administrative control</option>
                                <option value="staffing">Staffing change</option>
                                <option value="training">Training</option>
                                <option value="follow_up_observation">Follow-up observation</option>
                                <option value="ppe">PPE</option>
                                <option value="process">Process</option>
                                <option value="temporary">Temporary</option>
                                <option value="permanent">Permanent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="caLibraryDueDays" class="form-label">Due days</label>
                            <input id="caLibraryDueDays" name="dueDays" class="form-control" type="number" min="1" value="30">
                        </div>
                        <div class="col-md-4">
                            <label for="caLibraryFollowUpDays" class="form-label">Follow-up days</label>
                            <input id="caLibraryFollowUpDays" name="followUpDays" class="form-control" type="number" min="1" placeholder="Optional">
                        </div>
                        <div class="col-md-6">
                            <label for="caLibraryRiskFactor" class="form-label">Risk factor / body area</label>
                            <input id="caLibraryRiskFactor" name="riskFactor" class="form-control" type="text" placeholder="manual_handling">
                        </div>
                        <div class="col-md-6">
                            <label for="caLibraryTaskType" class="form-label">Task type</label>
                            <input id="caLibraryTaskType" name="taskType" class="form-control" type="text" placeholder="lifting">
                        </div>
                        <div class="col-md-6">
                            <label for="caLibraryIndustry" class="form-label">Industry</label>
                            <input id="caLibraryIndustry" name="industry" class="form-control" type="text" placeholder="Optional">
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="caLibraryEvidenceRequired" name="evidenceRequired" class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label" for="caLibraryEvidenceRequired">Evidence required</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input id="caLibraryIsActive" name="isActive" class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label" for="caLibraryIsActive">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Accepted evidence types</label>
                            <div class="row g-2" id="caLibraryEvidenceTypes">
                                <?php foreach (['photo' => 'Photo', 'video' => 'Video', 'receipt' => 'Receipt', 'note' => 'Field note', 'worker_feedback' => 'Worker feedback', 'follow_up_observation' => 'Follow-up observation', 'document' => 'Document'] as $value => $label): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="caLibraryEvidenceType_<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" name="evidenceTypes[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                            <label class="form-check-label" for="caLibraryEvidenceType_<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="caLibrarySubmit">Save control</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="caRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="caRuleForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="caRuleModalTitle">New rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="caRuleUuid" name="uuid">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="caRuleLibraryItemUuid" class="form-label">Linked corrective action</label>
                            <select id="caRuleLibraryItemUuid" name="libraryItemUuid" class="form-select" required></select>
                        </div>
                        <div class="col-md-3">
                            <label for="caRuleAssessmentTypeInput" class="form-label">Assessment type</label>
                            <select id="caRuleAssessmentTypeInput" name="assessmentType" class="form-select">
                                <option value="reba">REBA</option>
                                <option value="rula">RULA</option>
                                <option value="niosh">NIOSH</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="caRuleWeight" class="form-label">Priority / order</label>
                            <input id="caRuleWeight" name="weight" class="form-control" type="number" min="0" value="100">
                        </div>
                        <div class="col-md-4">
                            <label for="caRuleRiskFactor" class="form-label">Risk factor</label>
                            <input id="caRuleRiskFactor" name="riskFactor" class="form-control" type="text" placeholder="manual_handling">
                        </div>
                        <div class="col-md-4">
                            <label for="caRuleMinScore" class="form-label">Score threshold</label>
                            <input id="caRuleMinScore" name="minScore" class="form-control" type="number" min="0" value="50">
                        </div>
                        <div class="col-md-4">
                            <label for="caRuleConfidenceThreshold" class="form-label">Confidence threshold</label>
                            <input id="caRuleConfidenceThreshold" name="confidenceThreshold" class="form-control" type="number" min="0" max="1" step="0.1" placeholder="0.7">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input id="caRuleIsActive" name="isActive" class="form-check-input" type="checkbox" checked>
                                <label class="form-check-label" for="caRuleIsActive">Rule active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="caRuleSubmit">Save rule</button>
                </div>
            </form>
        </div>
    </div>
</div>