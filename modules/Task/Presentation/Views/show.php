<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$taskId = (string) (($routeParams ?? [])['taskId'] ?? '');
$pageTitle = 'Task Detail';
$pagePurpose = 'Task profile showing operational context and linked workflow items.';
$pageActions = [
    ['label' => 'Edit Task', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'pencil', 'id' => 'btnEditTask'],
    ['label' => 'New Assessment', 'url' => '/assessments/new-manual?task=' . rawurlencode($taskId), 'class' => 'btn btn-outline-secondary', 'icon' => 'clipboard-data'],
    ['label' => 'Upload or Record Video', 'url' => '/assessments/video?task=' . rawurlencode($taskId), 'class' => 'btn btn-outline-secondary', 'icon' => 'camera-video', 'id' => 'btnTaskVideoCapture'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Tasks', 'url' => '/tasks'],
    ['label' => 'Task Detail'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eTaskId = htmlspecialchars($taskId, ENT_QUOTES, 'UTF-8');
$eOrgId = htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div id="taskShowPage"
    data-task-id="<?= $eTaskId ?>"
    data-org-id="<?= $eOrgId ?>"
    data-api-base="/api/v1/tasks/<?= $eTaskId ?>"
    data-feedback-api="/api/v1/worker-feedback"
    data-ca-api="/api/v1/corrective-actions">

    <!-- Task Info Card -->
    <div class="card mb-4" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-body">
            <div id="taskDetailAlert" class="mb-3 d-none"></div>
            <div class="d-flex align-items-start gap-3 flex-wrap">
                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                    style="width:64px;height:64px;background:var(--we-primary-light)">
                    <i class="bi bi-list-task fs-3" style="color:var(--we-primary)"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0" id="task-name-display">Loading…</h4>
                        <span id="task-status-badge"></span>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <span class="text-muted small"><i class="bi bi-upc-scan me-1"></i>Code: <strong id="task-code-display">—</strong></span>
                        <span class="text-muted small"><i class="bi bi-clock me-1"></i>Created: <strong id="task-created-display">—</strong></span>
                    </div>
                    <p class="text-muted mb-0" id="task-description-display">—</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Context Info Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1"><i class="bi bi-geo-alt me-1" style="color:var(--we-primary)"></i>Worksite</span>
                    <h5 class="fw-bold mb-0" id="task-worksite-display">—</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1"><i class="bi bi-diagram-3 me-1" style="color:#3B82F6"></i>Department</span>
                    <h5 class="fw-bold mb-0" id="task-dept-display">—</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1"><i class="bi bi-person-badge me-1" style="color:#10B981"></i>Job Role</span>
                    <h5 class="fw-bold mb-0" id="task-role-display">—</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1"><i class="bi bi-clipboard-pulse me-1" style="color:#8B5CF6"></i>Assessment Model</span>
                    <h5 class="fw-bold mb-1 text-uppercase" id="task-model-display">—</h5>
                    <div class="small text-muted" id="task-input-support-display">—</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cross-module Quick Actions -->
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm);transition:box-shadow .2s,transform .2s"
                onmouseenter="this.style.boxShadow='var(--we-shadow)';this.style.transform='translateY(-2px)'"
                onmouseleave="this.style.boxShadow='var(--we-shadow-sm)';this.style.transform='none'">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <h5 class="card-title mb-1"><i class="bi bi-clipboard-data me-2" style="color:var(--we-primary)"></i>Assessments</h5>
                            <p class="text-muted small mb-0">Ergonomic risk assessments for this task</p>
                        </div>
                        <span class="badge bg-label-primary fs-6 px-3 py-2" id="task-assessments-count">0</span>
                    </div>
                    <div class="mt-auto d-flex gap-2 flex-wrap pt-3 border-top">
                        <a href="/assessments/new-manual?task=<?= $eTaskId ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>New Assessment
                        </a>
                        <a href="/assessments/video?task=<?= $eTaskId ?>" class="btn btn-outline-secondary btn-sm" id="taskVideoAssessmentLink">
                            <i class="bi bi-camera-video me-1"></i>Upload or record
                        </a>
                        <a href="/assessments" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-eye me-1"></i>View All
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm);transition:box-shadow .2s,transform .2s"
                onmouseenter="this.style.boxShadow='var(--we-shadow)';this.style.transform='translateY(-2px)'"
                onmouseleave="this.style.boxShadow='var(--we-shadow-sm)';this.style.transform='none'">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <h5 class="card-title mb-1"><i class="bi bi-chat-square-text me-2" style="color:#F59E0B"></i>Worker Voice</h5>
                            <p class="text-muted small mb-0">Discomfort and feedback from workers</p>
                        </div>
                        <span class="badge bg-label-warning fs-6 px-3 py-2" id="task-feedback-count">0</span>
                    </div>
                    <div class="mt-auto d-flex gap-2 flex-wrap pt-3 border-top">
                        <a href="/worker-voice/new?task=<?= $eTaskId ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Submit Feedback
                        </a>
                        <a href="/worker-voice/trends" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-bar-chart me-1"></i>Trends
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm);transition:box-shadow .2s,transform .2s"
                onmouseenter="this.style.boxShadow='var(--we-shadow)';this.style.transform='translateY(-2px)'"
                onmouseleave="this.style.boxShadow='var(--we-shadow-sm)';this.style.transform='none'">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <h5 class="card-title mb-1"><i class="bi bi-shield-check me-2" style="color:#10B981"></i>Corrective Actions</h5>
                            <p class="text-muted small mb-0">Interventions and control measures</p>
                        </div>
                        <span class="badge bg-label-success fs-6 px-3 py-2" id="task-ca-count">0</span>
                    </div>
                    <div class="mt-auto d-flex gap-2 flex-wrap pt-3 border-top">
                        <a href="/corrective-actions/recommendations" class="btn btn-success btn-sm">
                            <i class="bi bi-list-check me-1"></i>Recommendations
                        </a>
                        <a href="/corrective-actions" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-eye me-1"></i>View Actions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="taskEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="taskEditAlert" class="mb-3"></div>
                <form id="taskEditForm" novalidate>
                    <input type="hidden" id="editTaskId" value="<?= $eTaskId ?>">
                    <div class="mb-3">
                        <label for="editTaskName" class="form-label fw-medium">Task Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editTaskName" name="name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskCode" class="form-label fw-medium">Task Code</label>
                        <input type="text" class="form-control" id="editTaskCode" name="taskCode">
                    </div>
                    <div class="mb-3">
                        <label for="editTaskWorksite" class="form-label fw-medium">Worksite</label>
                        <select class="form-select" id="editTaskWorksite" name="worksiteId">
                            <option value="">No specific worksite</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskDept" class="form-label fw-medium">Department</label>
                        <select class="form-select" id="editTaskDept" name="departmentId">
                            <option value="">No specific department</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskRole" class="form-label fw-medium">Job Role</label>
                        <select class="form-select" id="editTaskRole" name="jobRoleId">
                            <option value="">No specific job role</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskAssessmentModel" class="form-label fw-medium">Assessment Model <span class="text-danger">*</span></label>
                        <select class="form-select" id="editTaskAssessmentModel" name="assessmentModel" required>
                            <option value="">Select model</option>
                            <option value="reba">REBA</option>
                            <option value="rula">RULA</option>
                            <option value="niosh">NIOSH</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskDescription" class="form-label fw-medium">Description</label>
                        <textarea class="form-control" id="editTaskDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskStatus" class="form-label fw-medium">Status</label>
                        <select class="form-select" id="editTaskStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="taskEditSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>
