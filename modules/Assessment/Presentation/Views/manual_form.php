<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$organizationUuid = (string) ($organizationUuid ?? '');
$pageTitle = 'New Manual Assessment';
$pagePurpose = 'Score a task using REBA, RULA, or NIOSH inputs.';
$breadcrumbs = [
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'New Manual Assessment', 'url' => null],
];
$pageActions = [
    ['label' => 'Back to assessments', 'url' => '/assessments', 'class' => 'btn btn-outline-secondary', 'icon' => 'arrow-left'],
    ['label' => 'Upload video', 'url' => '/assessments/video', 'class' => 'btn btn-outline-secondary', 'icon' => 'camera-video'],
];

$preselectedTask = (string) (($query['task'] ?? '') ?: '');

$helpIcon = static function (string $text): string {
    return '<button type="button" class="btn btn-sm btn-icon text-muted p-0 ms-1 align-baseline" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '" aria-label="Help"><i class="bi bi-question-circle"></i></button>';
};
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    id="assessmentManualPage"
    data-api-base="<?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>/manual"
    data-tasks-api="<?= $organizationUuid !== '' ? '/api/v1/organizations/' . htmlspecialchars($organizationUuid, ENT_QUOTES, 'UTF-8') . '/tasks' : '/api/v1/tasks' ?>"
    data-task="<?= htmlspecialchars($preselectedTask, ENT_QUOTES, 'UTF-8') ?>">
    <section class="card" style="border-radius: var(--we-radius-xl); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        </div>
        <div class="card-body">
            <div id="assessmentManualAlert"></div>
            <form id="assessmentManualForm" novalidate>
                <input type="hidden" id="manualSubmitMode" name="submitMode" value="submit">
                <div class="bs-stepper wizard-numbered wizard-vertical" id="assessmentManualWizard">
                    <div class="bs-stepper-header flex-column align-items-stretch me-0 me-lg-4" role="tablist" aria-orientation="vertical">
                        <div class="step active" data-target="#manualStepContext">
                            <button type="button" class="step-trigger w-100 text-start" data-manual-step-trigger="0" aria-selected="true">
                                <span class="bs-stepper-circle">1</span>
                                <span class="bs-stepper-label">
                                    <span class="bs-stepper-title">Task Setup</span>
                                    <span class="bs-stepper-subtitle">Choose task and load its scoring model</span>
                                </span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#manualStepMetrics">
                            <button type="button" class="step-trigger w-100 text-start" data-manual-step-trigger="1" aria-selected="false">
                                <span class="bs-stepper-circle">2</span>
                                <span class="bs-stepper-label">
                                    <span class="bs-stepper-title">Scoring Inputs</span>
                                    <span class="bs-stepper-subtitle">Enter posture and force values</span>
                                </span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#manualStepImpact">
                            <button type="button" class="step-trigger w-100 text-start" data-manual-step-trigger="2" aria-selected="false">
                                <span class="bs-stepper-circle">3</span>
                                <span class="bs-stepper-label">
                                    <span class="bs-stepper-title">Impact Areas</span>
                                    <span class="bs-stepper-subtitle">Risk factors and body regions</span>
                                </span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#manualStepReview">
                            <button type="button" class="step-trigger w-100 text-start" data-manual-step-trigger="3" aria-selected="false">
                                <span class="bs-stepper-circle">4</span>
                                <span class="bs-stepper-label">
                                    <span class="bs-stepper-title">Review</span>
                                    <span class="bs-stepper-subtitle">Confirm and create</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="bs-stepper-content pt-4 pt-lg-0">
                        <section class="content active d-block" id="manualStepContext" data-manual-step-content="0">
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label for="manualTaskUuid" class="form-label">Task<?= $helpIcon('Choose the work activity being assessed. The system links the assessment to this task.') ?></label>
                                    <select id="manualTaskUuid" name="taskUuid" class="form-select" required>
                                        <option value="">Loading tasks...</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="manualModelDisplay" class="form-label">Assessment model<?= $helpIcon('The task decides the required assessment model. Change the task if a different model is needed.') ?></label>
                                    <input id="manualModelDisplay" class="form-control text-uppercase" value="Select a task first" readonly>
                                    <input id="manualModel" name="model" type="hidden" value="">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between gap-2 mt-4">
                                <a class="btn btn-outline-secondary" href="/assessments">Cancel</a>
                                <button type="button" class="btn btn-primary" data-manual-next>Next</button>
                            </div>
                        </section>

                        <section class="content d-none" id="manualStepMetrics" data-manual-step-content="1">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <h6 class="mb-0">Scoring inputs</h6>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="manualResetMetrics">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                </button>
                            </div>
                            <div class="row g-3" id="manualMetricsGrid"></div>
                            <div class="d-flex justify-content-between gap-2 mt-4">
                                <button type="button" class="btn btn-outline-secondary" data-manual-prev>Previous</button>
                                <button type="button" class="btn btn-primary" data-manual-next>Next</button>
                            </div>
                        </section>

                        <section class="content d-none" id="manualStepImpact" data-manual-step-content="2">
                            <div class="row g-4">
                                <div class="col-lg-5">
                                    <h6 class="mb-2">Risk factors</h6>
                                    <div class="d-grid gap-2" id="manualRiskFactors">
                                        <?php foreach (
                                            [
                                                'awkward_posture' => 'Awkward posture',
                                                'manual_handling' => 'Manual handling',
                                                'repetition' => 'Repetition',
                                                'forceful_exertion' => 'Forceful exertion',
                                                'static_posture' => 'Static posture',
                                            ] as $value => $label
                                        ): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="risk_<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" name="riskFactors[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                                <label class="form-check-label" for="risk_<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-lg-7">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <h6 class="mb-0">Body regions<?= $helpIcon('Select the affected body area and severity using the same region set used by the heat map workflow.') ?></h6>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="manualAddBodyRegion">
                                            <i class="bi bi-plus-lg me-1"></i>Add region
                                        </button>
                                    </div>
                                    <div class="d-grid gap-2" id="manualBodyRegions"></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between gap-2 mt-4">
                                <button type="button" class="btn btn-outline-secondary" data-manual-prev>Previous</button>
                                <button type="button" class="btn btn-primary" data-manual-next>Next</button>
                            </div>
                        </section>

                        <section class="content d-none" id="manualStepReview" data-manual-step-content="3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <span class="text-muted small d-block mb-2">Task</span>
                                        <div class="fw-semibold" id="manualSummaryTask">Not selected</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <span class="text-muted small d-block mb-2">Model</span>
                                        <div class="fw-semibold" id="manualSummaryModel">Not selected</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <span class="text-muted small d-block mb-2">Risk factors</span>
                                        <div class="fw-semibold" id="manualSummaryRiskFactors">None selected</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <span class="text-muted small d-block mb-2">Body regions</span>
                                        <div class="fw-semibold" id="manualSummaryBodyRegions">None added</div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-label-primary mt-4 mb-0">
                                Submit sends the assessment to reviewer queue immediately. Save as draft keeps it editable for later submission.
                            </div>
                            <div class="d-flex justify-content-between gap-2 mt-4">
                                <button type="button" class="btn btn-outline-secondary" data-manual-prev>Previous</button>
                                <div class="btn-group" role="group" aria-label="Manual assessment actions">
                                    <button type="submit" class="btn btn-primary" id="manualSubmitBtn" data-submit-mode="submit">
                                        <i class="bi bi-check-lg me-1"></i>Submit assessment
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown"
                                        data-bs-auto-close="true"
                                        aria-expanded="false"
                                        aria-label="More assessment actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button type="submit" class="dropdown-item" id="manualSaveDraftBtn" data-submit-mode="draft">
                                                <i class="bi bi-save me-2"></i>Save as draft
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>