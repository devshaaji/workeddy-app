<?php
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="card card-action mb-4">
    <div class="card-header align-items-center">
        <h3 class="card-title mb-0"><i class="bx bx-list-ul me-2"></i>Activity Timeline</h3>
    </div>
    <div class="card-body">
        <ul class="timeline ms-2" id="app-timeline" 
            data-entity-id="<?= $e($userId ?? $roleId ?? '') ?>" 
            data-entity-type="<?= isset($userId) ? 'user' : 'role' ?>" 
            data-empty-message="No activity logged yet.">
            <li class="timeline-item timeline-item-transparent text-center text-muted py-4">
                Loading activity logs...
            </li>
        </ul>
    </div>
</div>
