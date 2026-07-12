<?php

declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$organizationId = (string)(($routeParams ?? [])['id'] ?? ($organizationUuid ?? ''));
$pageTitle = 'Tasks';
$pagePurpose = 'Define the work being assessed and tracked across your organisation.';
$pageActions = [
    ['label' => 'Add Task', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'plus-lg', 'id' => 'btnAddTask'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Tasks'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eOrgId = htmlspecialchars($organizationId, ENT_QUOTES, 'UTF-8');
?>
<div class="flex-grow-1 py-4" id="tasksPage"
    data-api-base="/api/v1/organizations/<?= $eOrgId ?>/tasks"
    data-org-id="<?= $eOrgId ?>">

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Total Tasks</span>
                            <h3 class="mb-0 fw-bold" id="task-stat-total">—</h3>
                        </div>
                        <div class="rounded p-2 bg-label-primary text-heading">
                            <i class="bi bi-list-task fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Active</span>
                            <h3 class="mb-0 fw-bold" id="task-stat-active">—</h3>
                        </div>
                        <div class="rounded p-2 bg-label-success text-heading">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="task-search" class="form-label">Search</label>
                    <input type="search" class="form-control" id="task-search" placeholder="Search tasks…">
                </div>
                <div class="col-md-3">
                    <label for="task-worksite-filter" class="form-label">Worksite</label>
                    <select class="form-select" id="task-worksite-filter">
                        <option value="">All worksites</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="task-dept-filter" class="form-label">Department</label>
                    <select class="form-select" id="task-dept-filter">
                        <option value="">All departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="task-status-filter" class="form-label">Status</label>
                    <select class="form-select" id="task-status-filter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="task-model-filter" class="form-label">Assessment Model</label>
                    <select class="form-select" id="task-model-filter">
                        <option value="">All models</option>
                        <option value="reba">REBA</option>
                        <option value="rula">RULA</option>
                        <option value="niosh">NIOSH</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card" id="tasksCard" data-endpoint="/api/v1/organizations/<?= $eOrgId ?>/tasks">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="badge bg-label-primary" id="task-result-count">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th><button class="table-sort" data-sort="name">Task Name</button></th>
                        <th><button class="table-sort" data-sort="taskCode">Task Code</button></th>
                        <th><button class="table-sort" data-sort="worksiteName">Worksite</button></th>
                        <th><button class="table-sort" data-sort="departmentName">Department</button></th>
                        <th><button class="table-sort" data-sort="jobRoleName">Job Role</button></th>
                        <th><button class="table-sort" data-sort="assessmentModel">Model</button></th>
                        <th><button class="table-sort" data-sort="status">Status</button></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="tasksBody">
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading tasks...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalTitle"><i class="bi bi-plus-circle me-2" style="color:var(--we-primary)"></i>Add Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="taskModalAlert" class="mb-3"></div>
                <form id="taskForm" novalidate>
                    <input type="hidden" id="taskId">
                    <div class="mb-3">
                        <label for="taskName" class="form-label fw-medium">Task Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="taskName" name="name" required placeholder="e.g. Box Lifting – Warehouse Line">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="taskCode" class="form-label fw-medium">Task Code</label>
                        <input type="text" class="form-control" id="taskCode" name="taskCode" placeholder="e.g. WH-BOX-001">
                    </div>
                    <div class="mb-3">
                        <label for="taskWorksite" class="form-label fw-medium">Worksite</label>
                        <select class="form-select" id="taskWorksite" name="worksiteId">
                            <option value="">No specific worksite</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="taskDept" class="form-label fw-medium">Department</label>
                        <select class="form-select" id="taskDept" name="departmentId">
                            <option value="">No specific department</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="taskJobRole" class="form-label fw-medium">Job Role</label>
                        <select class="form-select" id="taskJobRole" name="jobRoleId">
                            <option value="">No specific job role</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="taskAssessmentModel" class="form-label fw-medium">Assessment Model <span class="text-danger">*</span></label>
                        <select class="form-select" id="taskAssessmentModel" name="assessmentModel" required>
                            <option value="">Select model</option>
                            <option value="reba">REBA</option>
                            <option value="rula">RULA</option>
                            <option value="niosh">NIOSH</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="taskDescription" class="form-label fw-medium">Description</label>
                        <textarea class="form-control" id="taskDescription" name="description" rows="3" placeholder="Describe the task, including frequency, tools used, and physical demands."></textarea>
                    </div>
                    <div class="mb-3" id="taskStatusGroup" style="display:none">
                        <label for="taskStatus" class="form-label fw-medium">Status</label>
                        <select class="form-select" id="taskStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="taskSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Task</button>
            </div>
        </div>
    </div>
</div>
