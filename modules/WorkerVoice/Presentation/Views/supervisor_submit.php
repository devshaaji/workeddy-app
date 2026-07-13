<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Submit Supervisor Feedback';
$pagePurpose = 'Worker voice and discomfort reporting';
$pageActions = [
    ['label' => 'Supervisor Trends', 'url' => '/worker-voice/supervisor/trends', 'class' => 'btn btn-outline-secondary', 'icon' => 'graph-up'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice', 'class' => 'btn btn-primary', 'icon' => 'chat-square-text'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice'],
    ['label' => 'Supervisor Feedback', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="py-4" id="supervisorFeedbackSubmitPage" data-requires-organization-scope="true" data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-1">Supervisor Observation</h5>
        </div>
        <div class="card-body">
            <form id="supervisorFeedbackForm" class="row g-3">
                <div class="col-md-12">
                    <label class="form-label" for="supervisorAssessmentSelect">Assessment</label>
                    <select class="form-select" id="supervisorAssessmentSelect" name="assessmentUuid" required>
                        <option value="">Select assessment</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorWorksiteDisplay">Worksite</label>
                    <input class="form-control" id="supervisorWorksiteDisplay" value="Pulled from selected assessment" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorDepartmentDisplay">Department</label>
                    <input class="form-control" id="supervisorDepartmentDisplay" value="Pulled from selected assessment" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorJobRoleDisplay">Job Role</label>
                    <input class="form-control" id="supervisorJobRoleDisplay" value="Pulled from selected assessment" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorBodyRegion">Body Region</label>
                    <select class="form-select" id="supervisorBodyRegion" name="bodyRegion">
                        <option value="">Select body region</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorRiskLevel">Observed Risk Level</label>
                    <select class="form-select" id="supervisorRiskLevel" name="observedRiskLevel" required>
                        <option value="">Select risk level</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="very_high">Very High</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorIssueType">Observed Issue Type</label>
                    <select class="form-select" id="supervisorIssueType" name="observedIssueType" required>
                        <option value="">Select issue type</option>
                        <option value="posture">Posture</option>
                        <option value="force">Force</option>
                        <option value="repetition">Repetition</option>
                        <option value="reach">Reach</option>
                        <option value="equipment">Equipment</option>
                        <option value="workflow">Workflow</option>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Frequency</label><input class="form-control" type="number" min="0" max="5" name="frequencyLevel" value="0"></div>
                <div class="col-md-2"><label class="form-label">Severity</label><input class="form-control" type="number" min="0" max="5" name="severityLevel" value="0"></div>
                <div class="col-12"><label class="form-label">Suggested Change</label><input class="form-control" name="suggestedChange" placeholder="What should be changed to reduce the risk?"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div>
                <div class="col-12"><button type="submit" class="btn btn-primary">Submit Supervisor Feedback</button></div>
            </form>
            <div id="supervisorFeedbackAlert" class="mt-3"></div>
        </div>
    </div>
</div>