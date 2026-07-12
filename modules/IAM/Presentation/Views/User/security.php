<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'User Security';
$pagePurpose = 'Manage sensitive account controls for a selected user.';
$userId = (string) ($routeParams['id'] ?? '');
$pageActions = [
    ['label' => 'Back to Users', 'url' => '/users', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$user = $user ?? [];
$userActions = $userActions ?? [];
$can = $can ?? [];
$profile = $user['profile'] ?? [];
$membership = $user['membership'] ?? [];
$roleLabel = $membership['roleName'] ?? $membership['roleSlug'] ?? null;
$display = static fn(mixed $value): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : '--'), ENT_QUOTES, 'UTF-8');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Password Reset</h3>
            </div>
            <div class="card-body">
                <form class="row g-4" id="iam-user-password-form" action="/api/v1/iam/users/<?= $userId ?>/password" method="post" data-user-id="<?= $userId ?>" data-status="<?= htmlspecialchars((string) ($profile['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-iam-form="user-password">
                    <div class="col-md-6">
                        <label class="form-label" for="new_password">New temporary password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Temporary password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm password">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="reset_reason">Reason</label>
                        <textarea id="reset_reason" name="reset_reason" class="form-control" rows="3" placeholder="Reason for the reset or forced security action"></textarea>
                    </div>
                    <?php if (!empty($can['resetPasswords'])): ?>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                        </div>
                    <?php endif; ?>
                </form>
                <div id="iam-user-security-feedback" class="d-none mt-4" data-form-feedback></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Active Sessions</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>IP Address</th>
                                <th>Last Seen</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="iam-user-security-sessions" data-iam-table-body data-empty-colspan="5">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Session records appear here.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Account State</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-4">
                    <dt class="col-6">Current Status</dt>
                    <dd class="col-6 text-end" id="iam-user-security-status"><?= $display($profile['status'] ?? null) ?></dd>
                    <dt class="col-6">Last Login</dt>
                    <dd class="col-6 text-end" id="iam-user-security-last-login"><?= $display($profile['lastLoginAt'] ?? null) ?></dd>
                    <dt class="col-6">Role</dt>
                    <dd class="col-6 text-end" id="iam-user-security-role"><?= $display($roleLabel) ?></dd>
                </dl>
                <div class="d-grid gap-2" id="iam-user-security-actions">
                    <?php foreach ($userActions as $action): ?>
                        <button
                            type="button"
                            class="btn btn-outline-<?= htmlspecialchars($action['variant'] ?? 'secondary', ENT_QUOTES, 'UTF-8') ?>"
                            data-user-action="<?= htmlspecialchars($action['key'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-action-method="<?= htmlspecialchars($action['method'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-action-url="<?= htmlspecialchars($action['url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-confirm-title="<?= htmlspecialchars($action['confirmTitle'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-confirm-text="<?= htmlspecialchars($action['confirmText'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-loading-text="<?= htmlspecialchars($action['loadingText'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-success-message="<?= htmlspecialchars($action['successMessage'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($action['label'] ?? 'Action', ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
