<?php

declare(strict_types=1);

$pageTitle = 'Create Account';
?>
<div class="auth-wrapper">
  <div class="auth-card" style="max-width:480px;">

    <!-- Brand -->
    <div class="auth-brand">
      <img src="/assets/img/workeddy.png" alt="WorkEddy logo" class="auth-brand-logo" />
    </div>

    <h1 class="auth-title">Create your workspace</h1>
    <p class="auth-subtitle">Free trial — no credit card required.</p>
    <form id="registrationForm" action="/api/v1/auth/register" method="POST" novalidate>
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="mb-3">
        <label class="form-label" for="regOrg">Organization name</label>
        <input class="form-control" id="regOrg" name="organization_name" type="text" required>
        <div class="form-text text-muted">This becomes your workspace identifier.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="regName">Your full name</label>
        <input class="form-control" id="regName" name="name" type="text" required autocomplete="name">
      </div>

      <div class="mb-3">
        <label class="form-label" for="regEmail">Work email</label>
        <input class="form-control" id="regEmail" name="email" type="email" required autocomplete="email">
      </div>


      <div class="mb-3">
        <label class="form-label" for="register-password">Password</label>
        <input class="form-control" id="register-password" name="password" type="password" required autocomplete="new-password">
      </div>
      <div class="mb-3">
        <label class="form-label" for="register-confirm-password">Confirm</label>
        <input class="form-control" id="register-confirm-password" name="password2" type="password" required autocomplete="new-password">
      </div>


      <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" id="registerSubmitBtn">
        <span class="spinner-border spinner-border-sm me-2 d-none" id="registerSpinner"></span>
        <span id="registerBtnText">Create workspace</span>
      </button>
    </form>

    <p class="text-center mb-0 small">
      Already have an account?
      <a href="/login" class="text-decoration-none fw-semibold text-primary">Sign in</a>
    </p>

  </div>
</div>