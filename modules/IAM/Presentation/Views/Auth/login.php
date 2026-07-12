<?php

declare(strict_types=1);

$pageTitle = 'Sign In';
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <img src="/assets/img/workeddy.png" alt="WorkEddy logo" class="auth-brand-logo" />
    </div>

    <!-- Step 1: Email & Password -->
    <div id="credentials-step">
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-subtitle">Sign in to your workspace to continue.</p>

      <form id="loginForm" method="POST" action="/api/v1/auth/login" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
          <label class="form-label" for="loginEmail">Email address</label>
          <input class="form-control" id="loginEmail" type="email" name="email" required autocomplete="email" autofocus>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0" for="loginPassword">Password</label>
            <a href="/forgot-password" class="text-decoration-none text-sm text-primary">Forgot password?</a>
          </div>
          <input class="form-control" id="loginPassword" type="password" name="password" required autocomplete="current-password">
        </div>

        <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" id="loginSubmitBtn">
          <span class="spinner-border spinner-border-sm me-2 d-none" id="loginSpinner"></span>
          <span id="loginBtnText">Sign in</span>
        </button>
      </form>

      <p class="text-center mb-0 small">
        Don't have an account?
        <a href="/register" class="text-decoration-none fw-semibold text-primary">Create workspace</a>
      </p>
    </div>

  </div>
</div>