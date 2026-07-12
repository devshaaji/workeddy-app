<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Notification Settings';
$pagePurpose = 'Configure global notification behavior and delivery providers.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Notifications', 'url' => null],
    ['label' => 'Settings', 'url' => null],
];
$pageActions = [
    ['label' => 'Logs', 'url' => '/notifications/logs', 'class' => 'btn btn-outline-secondary', 'icon' => 'journal-text'],
    ['label' => 'Templates', 'url' => '/notifications/templates', 'class' => 'btn btn-outline-secondary', 'icon' => 'file-text'],
];
$pageScripts = ['js/modules/notification.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="notificationSettingsPage">

    <!-- User context -->
    <div class="d-flex justify-content-end mb-3">
        <span class="badge bg-label-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: var(--we-radius); font-weight: 400;">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars((string) ($userName ?? $userId ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- Sender Identity -->
    <form id="notificationSettingsForm" novalidate>
        <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-envelope me-2" style="color: var(--we-primary)"></i>Sender identity
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="settingsDefaultFromName" class="form-label">Default from name <span class="text-danger">*</span></label>
                        <input type="text" id="settingsDefaultFromName" class="form-control" placeholder="e.g. WorkEddy">
                        <div class="form-text">Display name shown as the sender on all outbound notifications.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="settingsDefaultFromEmail" class="form-label">Default from email <span class="text-danger">*</span></label>
                        <input type="email" id="settingsDefaultFromEmail" class="form-control" placeholder="e.g. noreply@workeddy.com">
                        <div class="form-text">Email address used as the sender for all outbound email notifications.</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Delivery & Retry -->
        <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-arrow-repeat me-2" style="color: var(--we-primary)"></i>Delivery &amp; retry
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-2">
                            <input type="checkbox" class="form-check-input" id="settingsQueueEnabled" value="1">
                            <label class="form-check-label" for="settingsQueueEnabled">
                                Queue dispatch
                                <span class="d-block text-muted small fw-normal">Dispatch via platform queue instead of inline.</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-2">
                            <input type="checkbox" class="form-check-input" id="settingsFallbackEnabled" value="1">
                            <label class="form-check-label" for="settingsFallbackEnabled">
                                Fallback channels
                                <span class="d-block text-muted small fw-normal">Fallback to alternative channel on failure.</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="settingsRetryMaxAttempts" class="form-label">Max retry attempts</label>
                        <input type="number" id="settingsRetryMaxAttempts" class="form-control" min="0" max="10">
                    </div>
                    <div class="col-md-3">
                        <label for="settingsRetryDelaySeconds" class="form-label">Retry delay (seconds)</label>
                        <input type="number" id="settingsRetryDelaySeconds" class="form-control" min="0">
                    </div>
                </div>
            </div>
        </section>

        <!-- Timeouts -->
        <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock me-2" style="color: var(--we-primary)"></i>HTTP timeouts
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="settingsHttpTimeout" class="form-label">HTTP timeout (seconds)</label>
                        <input type="number" id="settingsHttpTimeout" class="form-control" min="1" max="120">
                        <div class="form-text">Maximum time to wait for a provider response.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="settingsHttpConnectTimeout" class="form-label">Connect timeout (seconds)</label>
                        <input type="number" id="settingsHttpConnectTimeout" class="form-control" min="1" max="60">
                        <div class="form-text">Maximum time to establish a provider connection.</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Provider Registry -->
        <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-diagram-3 me-2" style="color: var(--we-primary)"></i>Provider registry
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Define provider connection keys and API endpoints as a JSON array.
                    Each entry requires a <code>key</code>, <code>provider_type</code>, and <code>config</code> object.
                </p>
                <label for="settingsProviderList" class="form-label">Provider configuration (JSON)</label>
                <textarea id="settingsProviderList" class="form-control font-monospace" rows="8"
                    placeholder='[{"key":"smtp_main","provider_type":"smtp","config":{"host":"...","port":587}}]'></textarea>
                <div class="form-text">Invalid JSON will prevent saving. Use valid JSON array syntax.</div>
            </div>
        </section>

        <!-- Submit -->
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary" id="settingsSaveBtn">
                <i class="bi bi-floppy me-1"></i>Save settings
            </button>
        </div>
    </form>
</div>
