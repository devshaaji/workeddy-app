<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Privacy & Compliance';
$pagePurpose = 'Govern consent, retention, and sensitive media access.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Privacy', 'url' => null],
];
$pageActions = [];
$pageScripts = ['js/modules/privacy.js'];
$canRecordConsent = !empty($can['recordConsent']);
$canManageRetention = !empty($can['manageRetention']);
$canViewAudit = !empty($can['viewAudit']);
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="privacyOverviewPage">
    <!-- User context -->
    <div class="d-flex justify-content-end mb-3">
        <span class="badge bg-label-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: var(--we-radius); font-weight: 400;">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars((string) ($userName ?? $userId ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- Hero Banner -->
    <div class="card mb-4" style="border-radius: var(--we-radius-xl); box-shadow: var(--we-shadow); background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border: none;">
        <div class="card-body p-4 p-xl-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="rounded-3 p-2" style="background: var(--we-primary); color: #fff;">
                            <i class="bi bi-shield-check fs-4"></i>
                        </span>
                        <span class="badge bg-label-primary fs-6">Platform</span>
                    </div>
                    <h2 class="fw-bold mb-2" style="color: var(--we-heading)">Privacy &amp; Compliance</h2>
                    <p class="text-secondary mb-3 fs-5" style="max-width: 600px;">
                        WorkEddy is designed for ergonomic risk prevention and safety improvement, not worker discipline or productivity surveillance.
                    </p>
                    <p class="text-secondary mb-0">
                        Manage video consent capture, data retention policies, and access accountability for sensitive evidence.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <i class="bi bi-lock-fill" style="font-size: 5rem; color: var(--we-primary); opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <style>
        .privacy-nav-card:hover .card {
            box-shadow: var(--we-shadow) !important;
            transform: translateY(-2px);
        }
    </style>
    <section class="row g-4 mb-4" aria-label="Privacy tools">
        <!-- Consent -->
        <?php if ($canRecordConsent): ?>
        <div class="col-md-4">
            <a href="/privacy/consent" class="text-decoration-none privacy-nav-card">
                <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); transition: box-shadow .2s, transform .2s; cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="rounded p-2" style="background: var(--we-primary-light); color: var(--we-primary);">
                                <i class="bi bi-file-check fs-3"></i>
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2">Video Consent</h5>
                        <p class="text-muted small mb-3">
                            Record and manage worker video consent for ergonomic assessments.
                            Every video capture requires freely given, informed consent.
                        </p>
                        <span class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-right me-1"></i>Open consent
                        </span>
                    </div>
                </article>
            </a>
        </div>
        <?php endif; ?>

        <!-- Retention -->
        <?php if ($canManageRetention): ?>
        <div class="col-md-4">
            <a href="/privacy/retention" class="text-decoration-none privacy-nav-card">
                <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); transition: box-shadow .2s, transform .2s; cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="rounded p-2" style="background: var(--we-primary-light); color: var(--we-primary);">
                                <i class="bi bi-clock-history fs-3"></i>
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2">Retention Policy</h5>
                        <p class="text-muted small mb-3">
                            Configure how long video evidence is stored and whether raw footage is preserved or deleted after processing.
                        </p>
                        <span class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-right me-1"></i>Manage policy
                        </span>
                    </div>
                </article>
            </a>
        </div>
        <?php endif; ?>

        <!-- Access Log -->
        <?php if ($canViewAudit): ?>
        <div class="col-md-4">
            <a href="/privacy/video-access-log" class="text-decoration-none privacy-nav-card">
                <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); transition: box-shadow .2s, transform .2s; cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="rounded p-2" style="background: var(--we-primary-light); color: var(--we-primary);">
                                <i class="bi bi-journal-text fs-3"></i>
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2">Video Access Log</h5>
                        <p class="text-muted small mb-3">
                            View accountability records for who accessed sensitive video evidence, when, and why.
                        </p>
                        <span class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-right me-1"></i>View log
                        </span>
                    </div>
                </article>
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- Overview Cards Row -->
    <section class="row g-4" aria-label="Privacy at a glance">
        <div class="col-md-6">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2" style="color: var(--we-primary)"></i>About this section</h6>
                </div>
                <div class="card-body">
                    <p class="text-secondary small mb-0">
                        The Privacy &amp; Compliance section gives authorized administrators and platform operators
                        the tools to manage how video evidence is governed throughout its lifecycle.
                    </p>
                    <ul class="text-secondary small mt-3 mb-0 ps-3">
                        <li class="mb-1"><strong>Consent</strong> records capture who agreed to video recording, when, and under which privacy notice version.</li>
                        <li class="mb-1"><strong>Retention</strong> policies control whether raw video is kept, deleted, or de-identified after AI processing.</li>
                        <li class="mb-0"><strong>Access logs</strong> provide an immutable audit trail of every video evidence view.</li>
                    </ul>
                </div>
            </article>
        </div>
        <div class="col-md-6">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-shield-exclamation me-2" style="color: var(--we-primary)"></i>Data governance principles</h6>
                </div>
                <div class="card-body">
                    <ul class="text-secondary small mb-0 ps-3">
                        <li class="mb-2">Video recordings are used <strong>only</strong> for ergonomic risk prevention and safety improvement.</li>
                        <li class="mb-2">Recordings are <strong>never</strong> used for discipline, productivity surveillance, or performance evaluation.</li>
                        <li class="mb-2">Consent is freely given, informed, and documented before any video capture begins.</li>
                        <li class="mb-2">Retention policies are configurable per organization with clear operational consequences.</li>
                        <li class="mb-0">All video access is logged with actor, purpose, timestamp, and IP address.</li>
                    </ul>
                </div>
            </article>
        </div>
    </section>
</div>
