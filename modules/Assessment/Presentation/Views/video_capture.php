<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Task Video Capture';
$pagePurpose = 'Upload consented task video and queue reviewer-safe processing.';
$breadcrumbs = [
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Video Capture', 'url' => null],
];
$pageActions = [
    ['label' => 'Assessments', 'url' => '/assessments', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-ul'],
    ['label' => 'New manual', 'url' => '/assessments/new-manual', 'class' => 'btn btn-outline-secondary', 'icon' => 'plus-lg'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$organizationUuid = (string) ($organizationUuid ?? '');
$preselectedTask = (string) (($query['task'] ?? '') ?: '');
$videoCaptureConfig = is_array($videoCaptureConfig ?? null) ? $videoCaptureConfig : [];

$helpIcon = static function (string $text): string {
    return '<button type="button" class="btn btn-sm btn-icon text-muted p-0 ms-1 align-baseline" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '" aria-label="Help"><i class="bi bi-question-circle"></i></button>';
};

$configJson = json_encode($videoCaptureConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>

<section
    class="row g-4"
    id="assessmentVideoCapture"
    data-organization-uuid="<?= htmlspecialchars($organizationUuid, ENT_QUOTES, 'UTF-8') ?>"
    data-task="<?= htmlspecialchars($preselectedTask, ENT_QUOTES, 'UTF-8') ?>"
    data-video-config="<?= htmlspecialchars((string) $configJson, ENT_QUOTES, 'UTF-8') ?>">
    <div class="col-12 col-xl-7">
        <div class="card h-100 assessment-video-capture-card">
            <div class="card-body">
                <div id="assessmentVideoCaptureAlert" class="d-none"></div>

                <form id="assessmentVideoCaptureForm" enctype="multipart/form-data" novalidate>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="taskContext">Task<?= $helpIcon('Choose the work activity being captured in this video.') ?></label>
                            <select class="form-select" id="taskContext" name="taskContext">
                                <option value="">Loading tasks...</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="videoAssessmentModel" name="model" value="">

                    <div class="mt-4">
                        <label class="form-label d-block mb-2">Capture method<?= $helpIcon('Upload an existing file or record a new video on this device.') ?></label>
                        <div class="btn-group assessment-video-method-group w-100" role="group" aria-label="Capture method">
                            <input class="btn-check" type="radio" name="videoSourceMode" id="videoSourceUpload" value="upload" checked>
                            <label class="btn btn-outline-secondary" for="videoSourceUpload">Upload video</label>

                            <input class="btn-check" type="radio" name="videoSourceMode" id="videoSourceRecord" value="record">
                            <label class="btn btn-outline-secondary" for="videoSourceRecord">Record video</label>
                        </div>
                    </div>

                    <div id="videoValidationMessage" class="mt-3"></div>

                    <div class="mt-4">
                        <div id="uploadCapturePanel" class="assessment-capture-panel">
                            <div id="uploadDropzone" class="assessment-upload-dropzone" tabindex="0" role="button" aria-controls="videoFile">
                                <div class="assessment-upload-dropzone__icon">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                </div>
                                <div class="fw-semibold">Drag and drop video here</div>
                                <div class="text-muted small">or click to browse</div>
                                <div class="text-muted small mt-2" id="videoLimitsHelper">Accepted formats and limits load from your workspace settings.</div>
                            </div>
                            <input class="d-none" id="videoFile" name="file" type="file" accept="video/mp4,video/quicktime,video/webm">

                            <div class="assessment-selection-summary d-none mt-3" id="selectedVideoSummary">
                                <div>
                                    <div class="fw-semibold">Selected video</div>
                                    <div class="text-muted small" id="selectedVideoName">No file selected</div>
                                    <div class="text-muted small" id="selectedVideoMeta"></div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="removeSelectedVideo">Remove</button>
                            </div>
                        </div>

                        <div id="recordCapturePanel" class="assessment-capture-panel d-none">
                            <div class="assessment-record-panel">
                                <div class="assessment-record-preview">
                                    <video id="recordingPreview" class="assessment-record-preview__video d-none" autoplay muted playsinline></video>
                                    <div class="assessment-record-preview__placeholder" id="recordingPreviewPlaceholder">
                                        <i class="bi bi-camera-video"></i>
                                        <span>Camera not started</span>
                                    </div>
                                </div>
                                <div class="assessment-record-panel__actions">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-danger" id="recordToggleBtn" disabled>Start recording</button>
                                        <button type="button" class="btn btn-outline-secondary d-none" id="discardRecordingBtn">Discard</button>
                                    </div>
                                    <div class="small text-muted" id="recordingStateText">Camera not started</div>
                                    <div class="d-flex flex-wrap gap-3 small">
                                        <span><strong>Elapsed:</strong> <span id="recordingElapsedText">00:00</span></span>
                                        <span><strong>Limit:</strong> <span id="recordingLimitText">Loading...</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mt-4">
                        <div class="form-check mb-3">
                            <input class="form-check-input" id="acceptedNotice" name="acceptedNotice" type="checkbox" value="1" required>
                            <label class="form-check-label" for="acceptedNotice">I confirm the worker consent and upload notice were accepted.<?= $helpIcon('Submission is blocked until this confirmation is checked.') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" id="faceBlurRequested" name="faceBlurRequested" type="checkbox" value="1" checked>
                            <label class="form-check-label" for="faceBlurRequested">Blur faces before reviewer access<?= $helpIcon('Faces will be blurred in the reviewer playback version when processing completes.') ?></label>
                            <div class="text-muted small mt-1">Faces will be blurred in the reviewer playback version when processing completes.</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="submitVideoCapture">Upload and queue processing</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card h-100 assessment-video-status-card">
            <div class="card-header">
                <p class="text-muted small mb-0">Preview, validation, upload progress, and processing queue state update here.</p>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <div class="assessment-preview-shell" id="videoPreviewShell">
                    <video id="videoPreview" class="assessment-preview-shell__video d-none" controls preload="metadata" playsinline></video>
                    <div class="assessment-preview-shell__placeholder" id="videoPreviewPlaceholder">
                        <i class="bi bi-camera-video"></i>
                        <span>Select or record a video to preview it here.</span>
                    </div>
                </div>

                <div class="d-none" id="uploadProgressWrap">
                    <div class="d-flex justify-content-between align-items-center small mb-2">
                        <span class="fw-semibold">Upload progress</span>
                        <span id="uploadProgressText">0%</span>
                    </div>
                    <div class="progress" style="height: 0.5rem;">
                        <div class="progress-bar" id="uploadProgressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                <dl class="row small mb-0" id="videoCaptureStatus">
                    <dt class="col-5">Task</dt>
                    <dd class="col-7 text-muted">Waiting</dd>
                    <dt class="col-5">Model</dt>
                    <dd class="col-7 text-muted">Not selected</dd>
                    <dt class="col-5">Capture</dt>
                    <dd class="col-7 text-muted">Upload video</dd>
                    <dt class="col-5">Consent</dt>
                    <dd class="col-7 text-muted">Not confirmed</dd>
                    <dt class="col-5">Video</dt>
                    <dd class="col-7 text-muted">Not selected</dd>
                    <dt class="col-5">Upload</dt>
                    <dd class="col-7 text-muted">Idle</dd>
                    <dt class="col-5">Processing</dt>
                    <dd class="col-7 text-muted">Not queued</dd>
                    <dt class="col-5">Job</dt>
                    <dd class="col-7 text-muted">Not available</dd>
                </dl>
            </div>
        </div>
    </div>
</section>