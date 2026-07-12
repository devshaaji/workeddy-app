<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'My Profile';
$pagePurpose = 'View personal account information, contact details, and recent activity.';
$pageScripts = ['js/iam.js'];
$profile = $profile ?? [];
$profileData = $profile['profile'] ?? [];
$membership = $profile['membership'] ?? [];
$roleLabel = $membership['roleName'] ?? $membership['roleSlug'] ?? null;
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$display = static fn(mixed $value): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : '--'), ENT_QUOTES, 'UTF-8');
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr($parts[0] ?? '-', 0, 1) . substr($parts[1] ?? '', 0, 1)) ?: '--';
};
require $v2Root . '/shared/Views/Partials/page_header.php';
$profileTab = 'overview';
require __DIR__ . '/_tabs.php';
?>

<div class="card mb-4" data-iam-screen="profile-overview">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-center align-items-lg-end gap-4">
            <div class="avatar avatar-xl bg-primary text-white" id="iam-profile-initials">
                <?= $e($initials((string) ($profileData['fullName'] ?? ''))) ?>
            </div>
            <div class="flex-grow-1 text-center text-lg-start">
                <h4 class="mb-1" id="iam-profile-name"><?= $display($profileData['fullName'] ?? null) ?></h4>
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3 text-muted">
                    <span>Role: <span id="iam-profile-role"><?= $display($roleLabel) ?></span></span>
                    <span>Status: <span id="iam-profile-status"><?= $display($profileData['status'] ?? null) ?></span></span>
                    <span>Joined <span id="iam-profile-created"><?= $display($profileData['createdAt'] ?? null) ?></span></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <small class="text-uppercase text-muted">About</small>
                <ul class="list-unstyled my-3">
                    <li class="d-flex align-items-center mb-3"><span class="fw-medium me-2">Full Name:</span><span id="iam-profile-about-name"><?= $display($profileData['fullName'] ?? null) ?></span></li>
                    <li class="d-flex align-items-center mb-3"><span class="fw-medium me-2">Status:</span><span id="iam-profile-about-status"><?= $display($profileData['status'] ?? null) ?></span></li>
                    <li class="d-flex align-items-center"><span class="fw-medium me-2">Role:</span><span id="iam-profile-about-role"><?= $display($roleLabel) ?></span></li>
                </ul>

                <small class="text-uppercase text-muted">Contacts</small>
                <ul class="list-unstyled my-3 mb-0">
                    <li class="d-flex align-items-center mb-3"><span class="fw-medium me-2">Phone:</span><span id="iam-profile-phone"><?= $display($profileData['phone'] ?? null) ?></span></li>
                    <li class="d-flex align-items-center"><span class="fw-medium me-2">Email:</span><span id="iam-profile-email"><?= $display($profile['email'] ?? null) ?></span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Recent Activity</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Location</th>
                                <th>OS</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody id="iam-profile-activity-body" data-iam-table-body data-empty-colspan="5">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    Recent activity rows appear here when available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="pagination m-0 justify-content-end" id="iam-profile-activity-pagination" data-iam-pagination></ul>
            </div>
        </div>
    </div>
</div>
