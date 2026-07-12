<?php

declare(strict_types=1);

$pageTitle = 'Reset Password';
$userId = htmlspecialchars((string) ($vars['userId'] ?? $_GET['userId'] ?? $_GET['user_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$code = htmlspecialchars((string) ($vars['code'] ?? $_GET['code'] ?? $_GET['otp'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <img src="/assets/img/workeddy.png" alt="WorkEddy logo" class="auth-brand-logo" />
    </div>

    <h1 class="auth-title">Reset your password</h1>
    <p class="auth-subtitle">Enter a new secure password for your account.</p>

    <div class="alert alert-danger align-items-center gap-2 py-2 d-none" id="reset-password-feedback"></div>

    <form id="resetPasswordForm" method="POST" action="/api/v1/auth/reset-password" novalidate>
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="userId" id="reset_user_id" value="<?= $userId ?>">
      <input type="hidden" name="code" id="reset_code" value="<?= $code ?>">

      <div class="mb-3">
        <label class="form-label" for="new_password">New password</label>
        <input class="form-control" id="new_password" type="password" name="new_password" required autocomplete="new-password">
      </div>

      <div class="mb-4">
        <label class="form-label" for="confirm_password">Confirm password</label>
        <input class="form-control" id="confirm_password" type="password" name="confirm_password" required autocomplete="new-password">
      </div>

      <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" id="resetSubmitBtn">
        <span class="spinner-border spinner-border-sm me-2 d-none" id="resetSpinner"></span>
        <span id="resetBtnText">Reset password</span>
      </button>
    </form>

    <p class="text-center mb-0 small">
      <a href="/login" class="text-decoration-none text-primary">
        <i class="bi bi-arrow-left me-1"></i>Back to sign in
      </a>
    </p>

  </div>
</div>