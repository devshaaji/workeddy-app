<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Assign User Role';
$pagePurpose = 'Move a user onto the correct role while preserving audit context.';
$userId = (string) ($routeParams['id'] ?? '');
$pageActions = [
    ['label' => 'Back to Users', 'url' => '/users', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$user = $user ?? [];
$can = $can ?? [];
$profile = $user['profile'] ?? [];
$membership = $user['membership'] ?? [];
$roleLabel = $membership['roleName'] ?? $membership['roleSlug'] ?? null;
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<form class="card mb-4" id="iam-user-role-form" action="/api/v1/iam/users/<?= $userId ?>/role" method="post" data-user-id="<?= $userId ?>" data-iam-form="user-role">
    <div class="card-header">
        <h3 class="card-title mb-0">Role Assignment</h3>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label" for="user">User</label>
                <input type="text" id="user" class="form-control" value="<?= $e($profile['fullName'] ?? '') ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="current_role">Current role</label>
                <input type="text" id="current_role" class="form-control" value="<?= $e($roleLabel) ?>" readonly>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="role_slug">New role</label>
                <select id="role_slug" name="role_slug" class="form-select" data-selected-role="<?= $e($membership['roleSlug'] ?? '') ?>">
                    <option value="">Select role</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <?php if (!empty($can['assignRoles'])): ?><button type="submit" class="btn btn-primary w-100">Assign Role</button><?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="reason">Reason</label>
                <textarea id="reason" name="reason" class="form-control" rows="3" placeholder="Reason for assignment"></textarea>
            </div>
        </div>
        <div id="iam-role-assignment-feedback" class="d-none mt-4" data-form-feedback></div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">Available Roles</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Risk</th>
                        <th>Users</th>
                    </tr>
                </thead>
                <tbody id="iam-role-assignment-catalog" data-iam-table-body data-empty-colspan="4">
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Role options, descriptions, and membership counts appear here when records are available.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ul class="pagination m-0 justify-content-end" id="iam-role-assignment-pagination" data-iam-pagination></ul>
    </div>
</div>
