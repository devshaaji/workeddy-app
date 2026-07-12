<?php

declare(strict_types=1);

/**
 * Shared System Confirmation & Alert Dialog (Modal)
 * Shared across both app.php and auth.php layouts.
 */
?>
<div class="modal fade" id="systemUxDialogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title d-flex align-items-center gap-2">
                    <i data-dialog-icon class="bi bi-info-circle text-primary"></i>
                    <span data-dialog-title>Notice</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" data-dialog-body></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dialog-cancel>Cancel</button>
                <button type="button" class="btn btn-primary" data-dialog-ok>OK</button>
            </div>
        </div>
    </div>
</div>
