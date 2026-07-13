<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Wrong Organization Scope';
$message = $message ?? 'You do not have access to this page in the current organization scope.';
$organizationName = $organizationName ?? null;
$organizationUuid = $organizationUuid ?? null;
$suggestedAction = $suggestedAction ?? 'Switch to the correct organization scope to continue.';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="flex-shrink-0 rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-signpost-split fs-3"></i>
                    </div>
                    <div>
                        <h1 class="h3 mb-1">Wrong organization scope</h1>
                        <p class="text-muted mb-0">This page is available, but not in your current context.</p>
                    </div>
                </div>

                <div class="alert alert-warning mb-4" role="alert">
                    <?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <?php if (($organizationName !== null && $organizationName !== '') || ($organizationUuid !== null && $organizationUuid !== '')): ?>
                    <div class="mb-4">
                        <h2 class="h6 text-uppercase text-muted mb-2">Target organization</h2>
                        <div class="fw-semibold"><?= htmlspecialchars((string) ($organizationName ?: $organizationUuid), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($organizationName !== null && $organizationName !== '' && $organizationUuid !== null && $organizationUuid !== ''): ?>
                            <div class="text-muted small"><?= htmlspecialchars((string) $organizationUuid, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="mb-4 text-secondary"><?= htmlspecialchars((string) $suggestedAction, ENT_QUOTES, 'UTF-8') ?></p>

                <div class="d-flex flex-wrap gap-2">
                    <a href="/profile" class="btn btn-primary">Go to profile</a>
                    <a href="/organizations" class="btn btn-outline-secondary">View organizations</a>
                </div>
            </div>
        </div>
    </div>
</div>
