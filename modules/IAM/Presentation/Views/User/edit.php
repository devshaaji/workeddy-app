<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Edit User';
$pagePurpose = 'Update profile fields without mixing role and security workflows into one form.';
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
$display = static fn(mixed $value): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : '--'), ENT_QUOTES, 'UTF-8');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<form id="iam-user-edit-form" action="/api/v1/iam/users/<?= $userId ?>" method="post" data-user-id="<?= $userId ?>" autocomplete="off" data-iam-form="user-edit">
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">Profile Attributes</h3>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?= $e($profile['fullName'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= $e($user['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone number</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?= $e($profile['phone'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Current Access</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Role</dt>
                        <dd class="col-7 text-end" id="iam-edit-user-role"><?= $display($roleLabel) ?></dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7 text-end" id="iam-edit-user-status"><?= $display($profile['status'] ?? null) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <div id="iam-user-edit-feedback" class="d-none mt-4" data-form-feedback></div>
    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
        <?php if (!empty($can['viewUsers'])): ?><a href="/users/<?= $userId ?>/security" class="btn btn-outline-secondary">Open Security</a><?php endif; ?>
        <?php if (!empty($can['assignRoles'])): ?><a href="/users/<?= $userId ?>/role" class="btn btn-outline-secondary">Assign Role</a><?php endif; ?>
        <?php if (!empty($can['updateUsers'])): ?><button type="submit" class="btn btn-primary">Save Changes</button><?php endif; ?>
    </div>
</form>
