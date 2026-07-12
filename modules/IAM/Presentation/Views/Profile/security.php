<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Profile Security';
$pagePurpose = 'Change your password and review personal security posture.';
$pageScripts = ['js/iam.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
$profileTab = 'security';
require __DIR__ . '/_tabs.php';
?>

<div class="card" data-iam-screen="profile-security">
    <div class="card-header">
        <h3 class="card-title mb-0">Change Password</h3>
    </div>
    <div class="card-body">
        <form id="iam-profile-password-form" action="/api/v1/iam/profile/password" method="post" autocomplete="off" data-iam-form="profile-password">
            <div class="mb-4">
                <label class="form-label" for="current_password">Current password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Current password">
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="new_password">New password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="New password">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="confirm_password">Confirm password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password">
                </div>
                <div class="col-12">
                    <div id="iam-profile-security-feedback" class="d-none" data-form-feedback></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Update Password</button>
        </form>
    </div>
</div>
