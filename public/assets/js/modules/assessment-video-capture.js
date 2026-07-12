(function (window, document) {
  'use strict';

  var root = document.getElementById('assessmentVideoCapture');
  if (!root || !window.App) { return; }

  var orgUuid = root.getAttribute('data-organization-uuid') || '';
  var preselectedTaskUuid = root.getAttribute('data-task') || '';
  var config = parseConfig(root.getAttribute('data-video-config'));
  var form = document.getElementById('assessmentVideoCaptureForm');
  var taskSelect = document.getElementById('taskContext');
  var modelSelect = document.getElementById('videoAssessmentModel');
  var submitButton = document.getElementById('submitVideoCapture');
  var consentCheckbox = document.getElementById('acceptedNotice');
  var faceBlurCheckbox = document.getElementById('faceBlurRequested');
  var fileInput = document.getElementById('videoFile');
  var dropzone = document.getElementById('uploadDropzone');
  var uploadPanel = document.getElementById('uploadCapturePanel');
  var recordPanel = document.getElementById('recordCapturePanel');
  var videoPreview = document.getElementById('videoPreview');
  var videoPreviewPlaceholder = document.getElementById('videoPreviewPlaceholder');
  var recordingPreview = document.getElementById('recordingPreview');
  var recordingPreviewPlaceholder = document.getElementById('recordingPreviewPlaceholder');
  var selectedVideoSummary = document.getElementById('selectedVideoSummary');
  var selectedVideoName = document.getElementById('selectedVideoName');
  var selectedVideoMeta = document.getElementById('selectedVideoMeta');
  var removeSelectedVideoButton = document.getElementById('removeSelectedVideo');
  var recordToggleButton = document.getElementById('recordToggleBtn');
  var discardRecordingButton = document.getElementById('discardRecordingBtn');
  var recordingStateText = document.getElementById('recordingStateText');
  var recordingElapsedText = document.getElementById('recordingElapsedText');
  var recordingLimitText = document.getElementById('recordingLimitText');
  var uploadProgressWrap = document.getElementById('uploadProgressWrap');
  var uploadProgressText = document.getElementById('uploadProgressText');
  var uploadProgressBar = document.getElementById('uploadProgressBar');
  var validationTarget = document.getElementById('videoValidationMessage');
  var statusBox = document.getElementById('videoCaptureStatus');
  var limitsHelper = document.getElementById('videoLimitsHelper');
  var sourceModes = Array.prototype.slice.call(document.querySelectorAll('input[name="videoSourceMode"]'));

  var state = {
    tasks: [],
    selectedVideo: null,
    selectedVideoUrl: '',
    selectedVideoDurationSeconds: 0,
    selectedVideoSource: '',
    uploading: false,
    queueing: false,
    stream: null,
    recorder: null,
    recorderChunks: [],
    recordingStartTime: 0,
    recordingTimer: null,
    recordingMimeType: '',
    recordingDurationSeconds: 0,
    recordSupported: supportsRecording(),
    status: {
      task: 'Waiting',
      model: 'Not selected',
      capture: 'Upload video',
      consent: 'Not confirmed',
      video: 'Not selected',
      upload: 'Idle',
      processing: 'Not queued',
      job: 'Not available'
    }
  };

  function parseConfig(raw) {
    try {
      return raw ? JSON.parse(raw) : {};
    } catch (error) {
      return {};
    }
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) { return meta.getAttribute('content') || ''; }
    var input = document.querySelector('input[name="_token"]');
    if (input) { return input.value || ''; }
    return '';
  }

  function alert(type, message) {
    App.ui.showAlert(type, message, '#assessmentVideoCaptureAlert');
  }

  function clearValidation() {
    App.ui.clearAlert(validationTarget);
  }

  function showValidation(message) {
    App.ui.showAlert('warning', message, validationTarget);
  }

  function showValidationErrors(errors, fallbackMessage) {
    var rendered = { fieldErrors: {}, formErrors: [] };

    if (App.forms && App.forms.showValidationErrors && form) {
      rendered = App.forms.showValidationErrors(form, errors || {});
    }

    if (rendered.formErrors.length) {
      showValidation(rendered.formErrors.join(' '));
    } else if (fallbackMessage && Object.keys(rendered.fieldErrors).length === 0) {
      showValidation(fallbackMessage);
    }

    return rendered;
  }

  function initTooltips(container) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
      return;
    }
    Array.prototype.forEach.call((container || document).querySelectorAll('[data-bs-toggle="tooltip"]'), function (el) {
      if (!bootstrap.Tooltip.getInstance(el)) {
        new bootstrap.Tooltip(el);
      }
    });
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(value || ''));
    return div.innerHTML;
  }

  function formatBytes(bytes) {
    var size = Number(bytes || 0);
    if (size <= 0) { return '0 B'; }
    var units = ['B', 'KB', 'MB', 'GB'];
    var index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1);
    var scaled = size / Math.pow(1024, index);
    return (scaled >= 100 || index === 0 ? Math.round(scaled) : scaled.toFixed(1)) + ' ' + units[index];
  }

  function formatDuration(seconds) {
    var total = Math.max(0, Math.round(Number(seconds || 0)));
    var mins = Math.floor(total / 60);
    var secs = total % 60;
    return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  }

  function acceptedFormats() {
    var formats = config.limits && Array.isArray(config.limits.allowedFormats) ? config.limits.allowedFormats : [];
    return formats.length ? formats : [
      { label: 'MP4', mime: 'video/mp4', extension: 'mp4' },
      { label: 'MOV', mime: 'video/quicktime', extension: 'mov' },
      { label: 'WebM', mime: 'video/webm', extension: 'webm' }
    ];
  }

  function maxVideoSizeBytes() {
    return Number(config.limits && config.limits.maxVideoSizeBytes) || 524288000;
  }

  function maxDurationSeconds() {
    return Number(config.limits && config.limits.maxDurationSeconds) || 120;
  }

  function captureMode() {
    var checked = sourceModes.find(function (node) { return node.checked; });
    return checked ? checked.value : 'upload';
  }

  function parseTaskList(res) {
    if (!res || !res.ok) { return []; }
    if (Array.isArray(res.data)) { return res.data; }
    if (res.data && Array.isArray(res.data.data)) { return res.data.data; }
    return [];
  }

  function selectedTask() {
    return state.tasks.find(function (item) {
      return String(item.id || item.uuid || '') === String(taskSelect ? taskSelect.value : '');
    }) || null;
  }

  function updateStatusRow(key, value) {
    state.status[key] = value;
    renderStatus();
  }

  function renderStatus() {
    var labels = [
      ['Task', state.status.task],
      ['Model', state.status.model],
      ['Capture', state.status.capture],
      ['Consent', state.status.consent],
      ['Video', state.status.video],
      ['Upload', state.status.upload],
      ['Processing', state.status.processing],
      ['Job', state.status.job]
    ];

    statusBox.innerHTML = labels.map(function (entry) {
      return '<dt class="col-5">' + escapeHtml(entry[0]) + '</dt><dd class="col-7">' + entry[1] + '</dd>';
    }).join('');
  }

  function previewValue(value, tone) {
    if (tone === 'muted') {
      return '<span class="text-muted">' + escapeHtml(value) + '</span>';
    }
    if (tone === 'success') {
      return '<span class="badge bg-label-success">' + escapeHtml(value) + '</span>';
    }
    if (tone === 'info') {
      return '<span class="badge bg-label-info">' + escapeHtml(value) + '</span>';
    }
    if (tone === 'primary') {
      return '<span class="badge bg-label-primary">' + escapeHtml(value) + '</span>';
    }
    if (tone === 'warning') {
      return '<span class="badge bg-label-warning">' + escapeHtml(value) + '</span>';
    }
    return '<span class="text-break">' + escapeHtml(value) + '</span>';
  }

  function syncLimitText() {
    var formats = acceptedFormats().map(function (item) { return item.label; }).join(', ');
    var duration = formatDuration(maxDurationSeconds());
    if (recordingLimitText) { recordingLimitText.textContent = duration; }
    if (limitsHelper) {
      limitsHelper.textContent = formats + '. Maximum ' + formatBytes(maxVideoSizeBytes()) + ' and ' + duration + '.';
    }
  }

  function resetPreview() {
    if (state.selectedVideoUrl && state.selectedVideoUrl.indexOf('blob:') === 0) {
      URL.revokeObjectURL(state.selectedVideoUrl);
    }
    state.selectedVideoUrl = '';
    if (videoPreview) {
      videoPreview.pause();
      videoPreview.removeAttribute('src');
      videoPreview.load();
      videoPreview.classList.add('d-none');
    }
    if (videoPreviewPlaceholder) {
      videoPreviewPlaceholder.classList.remove('d-none');
    }
  }

  function showPreview(url) {
    if (!videoPreview) { return; }
    if (state.selectedVideoUrl && state.selectedVideoUrl !== url && state.selectedVideoUrl.indexOf('blob:') === 0) {
      URL.revokeObjectURL(state.selectedVideoUrl);
    }
    state.selectedVideoUrl = url;
    videoPreview.src = url;
    videoPreview.classList.remove('d-none');
    if (videoPreviewPlaceholder) {
      videoPreviewPlaceholder.classList.add('d-none');
    }
  }

  function setSelectedVideo(file, durationSeconds, source) {
    state.selectedVideo = file;
    state.selectedVideoDurationSeconds = durationSeconds;
    state.selectedVideoSource = source;

    if (selectedVideoSummary) {
      selectedVideoSummary.classList.remove('d-none');
    }
    if (selectedVideoName) {
      selectedVideoName.textContent = file.name || 'Recorded video';
    }
    if (selectedVideoMeta) {
      selectedVideoMeta.textContent = formatBytes(file.size) + ' / ' + formatDuration(durationSeconds);
    }
    if (discardRecordingButton) {
      discardRecordingButton.classList.toggle('d-none', source !== 'record');
    }

    showPreview(URL.createObjectURL(file));
    updateStatusRow('video', previewValue(source === 'record' ? 'Recorded and ready' : 'Selected and ready', 'success'));
    updateStatusRow('upload', previewValue('Ready to upload', 'muted'));
    updateSubmitState();
  }

  function clearSelectedVideo() {
    state.selectedVideo = null;
    state.selectedVideoDurationSeconds = 0;
    state.selectedVideoSource = '';
    if (fileInput) {
      fileInput.value = '';
    }
    if (selectedVideoSummary) {
      selectedVideoSummary.classList.add('d-none');
    }
    if (selectedVideoName) {
      selectedVideoName.textContent = 'No file selected';
    }
    if (selectedVideoMeta) {
      selectedVideoMeta.textContent = '';
    }
    if (discardRecordingButton) {
      discardRecordingButton.classList.add('d-none');
    }
    resetPreview();
    updateStatusRow('video', previewValue('Not selected', 'muted'));
    updateStatusRow('upload', previewValue('Idle', 'muted'));
    updateStatusRow('processing', previewValue('Not queued', 'muted'));
    updateStatusRow('job', previewValue('Not available', 'muted'));
    updateSubmitState();
  }

  function selectedTaskName(task) {
    return task ? (task.name || task.taskName || task.taskCode || task.id || task.uuid || 'Selected task') : 'Waiting';
  }

  function syncTaskModel() {
    clearValidation();
    var task = selectedTask();
    var model = task && task.assessmentModel ? String(task.assessmentModel).toUpperCase() : '';
    var supportsVideo = !!(task && task.supportsVideo);

    if (modelSelect) { modelSelect.value = model.toLowerCase(); }

    updateStatusRow('task', task ? previewValue(selectedTaskName(task), null) : previewValue('Waiting', 'muted'));
    updateStatusRow('model', model ? previewValue(model, 'primary') : previewValue('Not selected', 'muted'));

    if (task && !supportsVideo) {
      showValidation('The selected task uses a manual-only assessment model. Choose a task that supports video capture.');
      updateStatusRow('video', previewValue('Blocked for this task', 'warning'));
    } else if (!state.selectedVideo) {
      updateStatusRow('video', previewValue('Not selected', 'muted'));
    }

    updateSubmitState();
  }

  function syncConsentStatus() {
    updateStatusRow('consent', consentCheckbox && consentCheckbox.checked ? previewValue('Confirmed', 'success') : previewValue('Not confirmed', 'muted'));
    updateSubmitState();
  }

  function updateCaptureModeUi() {
    var mode = captureMode();
    var caps = config.capabilities || {};

    if (uploadPanel) { uploadPanel.classList.toggle('d-none', mode !== 'upload'); }
    if (recordPanel) { recordPanel.classList.toggle('d-none', mode !== 'record'); }

    updateStatusRow('capture', previewValue(mode === 'record' ? 'Record video' : 'Upload video', 'muted'));

    if (mode === 'record' && caps.recordingAllowed === false) {
      showValidation('Recording is disabled for this workspace. Upload an existing video instead.');
    }

    if (mode === 'record' && !state.recordSupported) {
      showValidation('Recording is not supported on this device or browser. Upload a video instead.');
      updateRecordingState('Recording not supported on this device/browser');
    }

    if (mode === 'upload') {
      stopStream();
    } else if (mode === 'record' && caps.recordingAllowed !== false && state.recordSupported && !state.stream) {
      prepareRecording();
    }

    clearValidation();
    if (selectedTask() && !selectedTask().supportsVideo) {
      showValidation('The selected task uses a manual-only assessment model. Choose a task that supports video capture.');
    } else if (mode === 'record' && !state.recordSupported) {
      showValidation('Recording is not supported on this device or browser. Upload a video instead.');
    }

    updateSubmitState();
  }

  function supportsRecording() {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  }

  function pickRecordingMimeType() {
    if (!window.MediaRecorder || typeof window.MediaRecorder.isTypeSupported !== 'function') {
      return '';
    }
    var types = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
    for (var i = 0; i < types.length; i += 1) {
      if (window.MediaRecorder.isTypeSupported(types[i])) {
        return types[i];
      }
    }
    return '';
  }

  function updateRecordingState(text) {
    if (recordingStateText) {
      recordingStateText.textContent = text;
    }
  }

  function updateRecordingTimer() {
    if (!state.recordingStartTime) {
      recordingElapsedText.textContent = '00:00';
      return;
    }

    var elapsedSeconds = Math.floor((Date.now() - state.recordingStartTime) / 1000);
    recordingElapsedText.textContent = formatDuration(elapsedSeconds);

    if (elapsedSeconds >= maxDurationSeconds() && state.recorder && state.recorder.state === 'recording') {
      stopRecording();
    }
  }

  function stopRecordingTimer() {
    if (state.recordingTimer) {
      window.clearInterval(state.recordingTimer);
      state.recordingTimer = null;
    }
    state.recordingStartTime = 0;
    updateRecordingTimer();
  }

  function stopStream() {
    if (state.stream) {
      state.stream.getTracks().forEach(function (track) { track.stop(); });
      state.stream = null;
    }
    if (recordingPreview) {
      recordingPreview.pause();
      recordingPreview.srcObject = null;
      recordingPreview.classList.add('d-none');
    }
    if (recordingPreviewPlaceholder) {
      recordingPreviewPlaceholder.classList.remove('d-none');
    }
    if (state.recorder && state.recorder.state === 'recording') {
      state.recorder.stop();
    }
    state.recorder = null;
    state.recorderChunks = [];
    stopRecordingTimer();
    state.recordingDurationSeconds = 0;
    if (recordToggleButton) {
      recordToggleButton.disabled = !state.recordSupported;
      recordToggleButton.classList.remove('btn-outline-secondary');
      recordToggleButton.classList.add('btn-danger');
      recordToggleButton.textContent = 'Start recording';
    }
  }

  function prepareRecording() {
    clearValidation();

    if (!state.recordSupported) {
      showValidation('Recording is not supported on this device or browser. Upload a video instead.');
      updateRecordingState('Recording not supported on this device/browser');
      return;
    }

    updateRecordingState('Requesting camera permission');
    navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then(function (stream) {
      state.stream = stream;
      state.recordingMimeType = pickRecordingMimeType();
      if (recordingPreview) {
        recordingPreview.srcObject = stream;
        recordingPreview.classList.remove('d-none');
        recordingPreview.play().catch(function () {});
      }
      if (recordingPreviewPlaceholder) {
        recordingPreviewPlaceholder.classList.add('d-none');
      }
      updateRecordingState('Ready to record');
      if (recordToggleButton) {
        recordToggleButton.disabled = false;
        recordToggleButton.classList.remove('btn-outline-secondary');
        recordToggleButton.classList.add('btn-danger');
        recordToggleButton.textContent = 'Start recording';
      }
    }).catch(function (error) {
      updateRecordingState(error && error.name === 'NotAllowedError' ? 'Permission denied' : 'Camera unavailable');
      showValidation(error && error.name === 'NotAllowedError'
        ? 'Camera permission was denied. Allow camera access to record a task video.'
        : 'This device could not start camera recording. Upload a video instead.');
      if (recordToggleButton) {
        recordToggleButton.disabled = true;
      }
    });
  }

  function startRecording() {
    clearValidation();
    if (!state.stream) {
      prepareRecording();
      return;
    }

    state.recorderChunks = [];
    try {
      state.recorder = state.recordingMimeType ? new MediaRecorder(state.stream, { mimeType: state.recordingMimeType }) : new MediaRecorder(state.stream);
    } catch (error) {
      showValidation('Recording could not start on this browser. Upload a video instead.');
      updateRecordingState('Recording not supported on this device/browser');
      return;
    }

    state.recorder.ondataavailable = function (event) {
      if (event.data && event.data.size > 0) {
        state.recorderChunks.push(event.data);
      }
    };

    state.recorder.onstop = function () {
      var duration = Math.max(1, state.recordingDurationSeconds || Math.floor((Date.now() - state.recordingStartTime) / 1000) || 1);
      stopRecordingTimer();
      if (!state.recorderChunks.length) {
        updateRecordingState('Recording stopped');
        return;
      }

      var blobType = state.recorder.mimeType || state.recordingMimeType || 'video/webm';
      var blob = new Blob(state.recorderChunks, { type: blobType });
      var file = new File([blob], 'task-video-' + Date.now() + '.webm', { type: blobType });
      duration = Math.min(maxDurationSeconds(), duration);
      validateFile(file, duration).then(function () {
        setSelectedVideo(file, duration, 'record');
        updateRecordingState('Preview recorded video');
        if (recordToggleButton) {
          recordToggleButton.disabled = false;
          recordToggleButton.classList.remove('btn-outline-secondary');
          recordToggleButton.classList.add('btn-danger');
          recordToggleButton.textContent = 'Start recording';
        }
      }).catch(function (message) {
        showValidation(message);
        updateRecordingState('Recording stopped');
      });
    };

    state.recorder.start(250);
    state.recordingStartTime = Date.now();
    state.recordingDurationSeconds = 0;
    state.recordingTimer = window.setInterval(updateRecordingTimer, 250);
    updateRecordingState('Recording');
    if (recordToggleButton) {
      recordToggleButton.classList.remove('btn-danger');
      recordToggleButton.classList.add('btn-outline-secondary');
      recordToggleButton.textContent = 'Stop recording';
    }
    if (discardRecordingButton) {
      discardRecordingButton.classList.add('d-none');
    }
    updateStatusRow('video', previewValue('Recording in progress', 'warning'));
  }

  function stopRecording() {
    if (state.recorder && state.recorder.state === 'recording') {
      state.recordingDurationSeconds = Math.max(1, Math.floor((Date.now() - state.recordingStartTime) / 1000));
      state.recorder.stop();
      updateRecordingState('Recording stopped');
    }
  }

  function discardRecording() {
    clearSelectedVideo();
    updateRecordingState(state.stream ? 'Ready to record' : 'Camera not started');
  }

  function validateFile(file, knownDurationSeconds) {
    return new Promise(function (resolve, reject) {
      if (!file) {
        reject('Select or record a video before continuing.');
        return;
      }

      var extension = String(file.name || '').split('.').pop().toLowerCase();
      var mimeType = String(file.type || '').toLowerCase();
      var formats = acceptedFormats();
      var allowedExtensions = formats.map(function (item) { return String(item.extension || '').toLowerCase(); });
      var allowedMimeTypes = formats.map(function (item) { return String(item.mime || '').toLowerCase(); });

      if (allowedExtensions.indexOf(extension) === -1 && allowedMimeTypes.indexOf(mimeType) === -1) {
        reject('This file type is not supported. Please upload ' + formats.map(function (item) { return item.label; }).join(', ') + '.');
        return;
      }
      if (file.size > maxVideoSizeBytes()) {
        reject('This file is ' + formatBytes(file.size) + '. Maximum allowed size is ' + formatBytes(maxVideoSizeBytes()) + '.');
        return;
      }
      if (knownDurationSeconds && knownDurationSeconds > maxDurationSeconds()) {
        reject('This video is longer than the allowed ' + formatDuration(maxDurationSeconds()) + '.');
        return;
      }

      if (knownDurationSeconds) {
        resolve({ durationSeconds: knownDurationSeconds });
        return;
      }

      var tempVideo = document.createElement('video');
      tempVideo.preload = 'metadata';
      tempVideo.onloadedmetadata = function () {
        var durationSeconds = Math.round(tempVideo.duration || 0);
        URL.revokeObjectURL(tempVideo.src);
        if (!durationSeconds || !isFinite(durationSeconds)) {
          reject('This video could not be read. Try another file.');
          return;
        }
        if (durationSeconds > maxDurationSeconds()) {
          reject('This video is longer than the allowed ' + formatDuration(maxDurationSeconds()) + '.');
          return;
        }
        resolve({ durationSeconds: durationSeconds });
      };
      tempVideo.onerror = function () {
        reject('This video could not be read. Try another file.');
      };
      tempVideo.src = URL.createObjectURL(file);
    });
  }

  function handleChosenFile(file) {
    clearValidation();
    validateFile(file).then(function (result) {
      setSelectedVideo(file, result.durationSeconds, 'upload');
    }).catch(function (message) {
      clearSelectedVideo();
      showValidation(message);
    });
  }

  function setProgress(value) {
    var progress = Math.max(0, Math.min(100, Number(value || 0)));
    uploadProgressWrap.classList.remove('d-none');
    uploadProgressText.textContent = Math.round(progress) + '%';
    uploadProgressBar.style.width = progress + '%';
    updateStatusRow('upload', previewValue(progress >= 100 ? 'Complete' : 'Uploading ' + Math.round(progress) + '%', progress >= 100 ? 'success' : 'info'));
  }

  function resetProgress() {
    uploadProgressWrap.classList.add('d-none');
    uploadProgressText.textContent = '0%';
    uploadProgressBar.style.width = '0%';
  }

  function updateSubmitState() {
    var task = selectedTask();
    var mode = captureMode();
    var caps = config.capabilities || {};
    var blockedForTask = !!(task && !task.supportsVideo);
    var blockedForMode = (mode === 'record' && (!state.recordSupported || caps.recordingAllowed === false))
      || (mode === 'upload' && caps.uploadAllowed === false);
    var ready = !!(task && modelSelect && modelSelect.value && state.selectedVideo && consentCheckbox && consentCheckbox.checked && !blockedForTask && !blockedForMode && !state.uploading && !state.queueing);

    submitButton.disabled = !ready;
  }

  function loadTasks() {
    if (!orgUuid || !taskSelect) { return; }

    App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/tasks', { limit: 100 }).then(function (res) {
      var tasks = parseTaskList(res);
      state.tasks = tasks;

      if (!res.ok || !tasks.length) {
        taskSelect.innerHTML = '<option value="">No tasks available</option>';
        updateSubmitState();
        return;
      }

      taskSelect.innerHTML = '<option value="">Select task</option>' + tasks.map(function (task) {
        var id = task.id || task.uuid || '';
        var label = task.name || task.taskName || task.taskCode || id;
        var support = task.supportsVideo ? '' : ' (manual only)';
        return '<option value="' + escapeHtml(id) + '">' + escapeHtml(label + support) + '</option>';
      }).join('');

      if (preselectedTaskUuid) {
        taskSelect.value = preselectedTaskUuid;
      }

      syncTaskModel();
    });
  }

  function xhrUpload(url, formData) {
    return new Promise(function (resolve, reject) {
      var request = new XMLHttpRequest();
      var endpoint = App.config.baseUrl.replace(/\/$/, '') + url;

      request.open('POST', endpoint, true);
      request.setRequestHeader('Accept', 'application/json');
      var csrf = getCsrfToken();
      if (csrf) {
        request.setRequestHeader('X-CSRF-TOKEN', csrf);
      }

      request.upload.addEventListener('progress', function (event) {
        if (event.lengthComputable) {
          setProgress((event.loaded / event.total) * 100);
          submitButton.textContent = 'Uploading ' + Math.round((event.loaded / event.total) * 100) + '%';
        }
      });

      request.onreadystatechange = function () {
        if (request.readyState !== 4) { return; }
        var response = {};
        try {
          response = JSON.parse(request.responseText || '{}');
        } catch (error) {
          response = {};
        }

        if (request.status >= 200 && request.status < 300) {
          resolve(response);
          return;
        }

        reject({
          status: request.status,
          message: response.message || 'Video upload failed.',
          errors: response.errors || {}
        });
      };

      request.onerror = function () {
        reject({ status: 0, message: 'Network error while uploading video.' });
      };

      request.send(formData);
    });
  }

  function submit(event) {
    event.preventDefault();
    clearValidation();
    if (App.forms && App.forms.clearValidationErrors) {
      App.forms.clearValidationErrors(form);
    }

    var task = selectedTask();
    if (!task || !task.supportsVideo) {
      showValidation('Choose a task that supports video assessment before continuing.');
      return;
    }
    if (!state.selectedVideo) {
      showValidation('Select or record a valid video before continuing.');
      return;
    }
    if (!consentCheckbox.checked) {
      showValidation('Confirm the worker consent and upload notice before continuing.');
      return;
    }

    var payload = new FormData();
    payload.append('file', state.selectedVideo, state.selectedVideo.name || 'task-video.webm');
    payload.append('taskUuid', String(task.id || task.uuid || ''));
    payload.append('durationSeconds', String(Math.max(1, state.selectedVideoDurationSeconds || 1)));
    payload.append('consentTextVersion', String(config.consentVersion || 'workeddy-video-consent-v1'));
    payload.append('acceptedNotice', '1');
    payload.append('faceBlurRequested', faceBlurCheckbox.checked ? '1' : '0');

    state.uploading = true;
    state.queueing = false;
    submitButton.disabled = true;
    submitButton.textContent = 'Uploading 0%';
    setProgress(0);
    updateStatusRow('processing', previewValue('Waiting for upload', 'muted'));

    xhrUpload('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/assessments/video', payload).then(function (response) {
      state.uploading = false;
      state.queueing = true;
      setProgress(100);
      submitButton.textContent = 'Upload complete. Queuing processing...';
      updateStatusRow('processing', previewValue('Queued', 'info'));

      var data = response.data || {};
      var assessment = data.assessment || {};
      var upload = data.upload || {};
      var video = upload.video || {};
      var jobId = video.jobId || video.jobUuid || video.queueJobId || video.id || '';
      var model = String(assessment.model || modelSelect.value || '').toUpperCase();

      updateStatusRow('task', previewValue(selectedTaskName(task), null));
      updateStatusRow('model', model ? previewValue(model, 'primary') : previewValue('Not selected', 'muted'));
      updateStatusRow('consent', previewValue('Confirmed', 'success'));
      updateStatusRow('video', previewValue(state.selectedVideoSource === 'record' ? 'Recorded and ready' : 'Selected and ready', 'success'));
      updateStatusRow('upload', previewValue('Complete', 'success'));
      updateStatusRow('processing', previewValue(video.processingStatus || 'Queued', 'info'));
      updateStatusRow('job', previewValue(jobId ? ('#' + jobId) : 'Not available', jobId ? 'muted' : 'muted'));

      alert('success', 'Video queued for processing.');

      window.setTimeout(function () {
        state.queueing = false;
        submitButton.textContent = 'Video queued for processing';
        submitButton.disabled = true;
        if (assessment.uuid) {
          window.location.href = '/assessments/' + encodeURIComponent(assessment.uuid);
        }
      }, 1200);
    }).catch(function (error) {
      state.uploading = false;
      state.queueing = false;
      resetProgress();
      submitButton.textContent = 'Upload and queue processing';
      updateStatusRow('upload', previewValue('Failed', 'warning'));
      updateStatusRow('processing', previewValue('Not queued', 'muted'));
      var rendered = showValidationErrors(error && error.errors ? error.errors : {}, error && error.message ? error.message : 'Video upload failed.');
      if (!rendered.formErrors.length && Object.keys(rendered.fieldErrors).length === 0) {
        alert('danger', error.message || 'Video upload failed.');
      }
      updateSubmitState();
    });
  }

  function bindUploadUi() {
    dropzone.addEventListener('click', function () {
      fileInput.click();
    });
    dropzone.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        fileInput.click();
      }
    });
    ['dragenter', 'dragover'].forEach(function (eventName) {
      dropzone.addEventListener(eventName, function (event) {
        event.preventDefault();
        dropzone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach(function (eventName) {
      dropzone.addEventListener(eventName, function (event) {
        event.preventDefault();
        dropzone.classList.remove('is-dragover');
      });
    });
    dropzone.addEventListener('drop', function (event) {
      var file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
      if (file) {
        handleChosenFile(file);
      }
    });
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      if (file) {
        handleChosenFile(file);
      }
    });
    removeSelectedVideoButton.addEventListener('click', clearSelectedVideo);
  }

  function bindRecordingUi() {
    if (recordToggleButton) {
      recordToggleButton.disabled = !state.recordSupported;
      recordToggleButton.addEventListener('click', function () {
        if (state.recorder && state.recorder.state === 'recording') {
          stopRecording();
          return;
        }

        startRecording();
      });
    }
    discardRecordingButton.addEventListener('click', discardRecording);
  }

  function bindEvents() {
    sourceModes.forEach(function (input) {
      input.addEventListener('change', updateCaptureModeUi);
    });
    taskSelect.addEventListener('change', syncTaskModel);
    consentCheckbox.addEventListener('change', syncConsentStatus);
    form.addEventListener('submit', submit);
    bindUploadUi();
    bindRecordingUi();
  }

  syncLimitText();
  initTooltips(root);
  bindEvents();
  loadTasks();
  syncConsentStatus();
  renderStatus();
  updateCaptureModeUi();
  clearSelectedVideo();

  window.addEventListener('beforeunload', function () {
    stopStream();
    resetPreview();
  });
})(window, document);
