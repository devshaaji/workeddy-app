<?php

declare(strict_types=1);

$pageTitle = 'Verify Code';
$userId = htmlspecialchars((string) ($_SESSION['pending_auth']['userId'] ?? $_GET['userId'] ?? $_GET['user_id'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <img src="/assets/img/workeddy.png" alt="WorkEddy logo" class="auth-brand-logo" />
    </div>

    <div>
      <h1 class="auth-title">Two-Factor Authentication</h1>
      <p class="auth-subtitle">Enter the 6-digit code sent to your email.</p>

      <form id="verifyOtpForm" action="/api/v1/auth/verify-otp" method="post" autocomplete="one-time-code">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="userId" id="otp_user_id" value="<?= $userId ?>">
        <input type="hidden" name="code" id="otp_code" required>

        <div class="row g-2 justify-content-center mb-3" aria-label="One-time code">
          <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="col-2">
              <input
                type="text"
                class="form-control form-control-lg text-center font-monospace"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="1"
                data-otp-input="1"
                autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>"
                aria-label="Code digit <?= $i ?>"
                required>
            </div>
          <?php endfor; ?>
        </div>

        <div id="otp-timer" class="text-center text-muted small mb-3" data-expires-at="<?= htmlspecialchars((string) ($pendingAuthExpiresAt ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>

        <button class="btn btn-primary w-100 btn-lg mb-3" type="submit" id="otpSubmitBtn">
          <span class="spinner-border spinner-border-sm me-2 d-none" id="otpSpinner"></span>
          <span id="otpBtnText">Verify</span>
        </button>
      </form>

      <div class="text-center mt-2 small" id="iam-resend-otp-wrap">
        <span class="text-muted">Did not receive the code?</span>
        <button type="button" class="btn btn-link p-0 ms-1 align-baseline text-decoration-none fw-semibold text-primary" id="iam-resend-otp">Resend</button>
      </div>

      <button class="btn btn-link text-muted w-100 mt-3" type="button" id="backToLoginBtn">
        <i class="bi bi-arrow-left me-1"></i> Back to sign in
      </button>
    </div>

  </div>
</div>