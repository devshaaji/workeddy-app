<?php

declare(strict_types=1);

$pageTitle = 'Forgot Password';
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <img src="/assets/img/workeddy.png" alt="WorkEddy logo" class="auth-brand-logo" />
    </div>

    <h1 class="auth-title">Reset your password</h1>
    <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>

    <div class="alert py-2 d-none" id="forgot-password-feedback"></div>

    <form id="forgotPasswordForm" method="POST" action="/api/v1/auth/forgot-password" novalidate>
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <div class="mb-4">
        <label class="form-label" for="forgotEmail">Email address</label>
        <input class="form-control" id="forgotEmail" type="email" name="email" required autocomplete="email">
      </div>

      <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" id="forgotSubmitBtn">
        <span class="spinner-border spinner-border-sm me-2 d-none" id="forgotSpinner"></span>
        <span id="forgotBtnText">Send reset link</span>
      </button>
    </form>

    <p class="text-center mb-0 small">
      <a href="/login" class="text-decoration-none text-primary">
        <i class="bi bi-arrow-left me-1"></i>Back to sign in
      </a>
    </p>

  </div>
</div>