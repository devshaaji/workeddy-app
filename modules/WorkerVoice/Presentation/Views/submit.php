<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Submit Worker Feedback';
$pagePurpose = 'Worker voice and discomfort reporting';
$pageActions = [
    ['label' => 'Feedback register', 'url' => '/worker-voice', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-ul'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice'],
    ['label' => 'Submit', 'url' => null],
];
$prefillTask = (string) (($query['task'] ?? $query['taskUuid'] ?? '') ?: '');
$prefillAssessment = (string) (($query['assessment'] ?? $query['assessmentUuid'] ?? '') ?: '');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    class="container-xxl flex-grow-1 py-4"
    id="workerVoiceSubmitPage"
    data-prefill-task="<?= htmlspecialchars($prefillTask, ENT_QUOTES, 'UTF-8') ?>"
    data-prefill-assessment="<?= htmlspecialchars($prefillAssessment, ENT_QUOTES, 'UTF-8') ?>"
    data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div id="workerVoiceSubmitAlert"></div>
    <div class="row g-4">
        <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
            <div class="card-body">
                <form id="workerVoiceSubmitForm" novalidate>
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label for="workerVoiceAssessmentSelect" class="form-label">Assessment</label>
                            <select class="form-select" id="workerVoiceAssessmentSelect" name="assessmentUuid" required>
                                <option value="">Select assessment</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="workerVoiceWorksiteDisplay" class="form-label">Worksite</label>
                            <input type="text" class="form-control" id="workerVoiceWorksiteDisplay" value="Pulled from selected assessment" readonly>
                        </div>
                        <div class="col-md-4">
                            <label for="workerVoiceDepartmentDisplay" class="form-label">Department</label>
                            <input type="text" class="form-control" id="workerVoiceDepartmentDisplay" value="Pulled from selected assessment" readonly>
                        </div>
                        <div class="col-md-4">
                            <label for="workerVoiceJobRoleDisplay" class="form-label">Job Role</label>
                            <input type="text" class="form-control" id="workerVoiceJobRoleDisplay" value="Pulled from selected assessment" readonly>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="workerVoiceBodyRegion" class="form-label">Body region</label>
                            <select class="form-select" id="workerVoiceBodyRegion" name="bodyRegion" required>
                                <option value="">Select body region</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="workerVoiceAnonymous" name="anonymousStatus" checked>
                                <label class="form-check-label" for="workerVoiceAnonymous">Hide identity from dashboards and reports</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="workerVoiceHasDiscomfort" name="hasDiscomfort" checked>
                        <label class="form-check-label" for="workerVoiceHasDiscomfort">I felt discomfort during this task</label>
                    </div>
                    <div class="row g-3" id="workerVoiceQuestionGrid"></div>
                    <div class="mt-4">
                        <label for="workerVoiceSuggestedChange" class="form-label">Suggested change</label>
                        <textarea class="form-control" id="workerVoiceSuggestedChange" name="suggestedChange" rows="4" maxlength="500" placeholder="What would reduce discomfort or make reporting easier?"></textarea>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="workerVoiceSubmitBtn"><i class="bi bi-send me-1"></i>Submit feedback</button>
                        <a href="/worker-voice" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
