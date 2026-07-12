/**
 * Storage admin file manager.
 * Uses window.App (api, notify, ui, modals) from app.js. No frameworks.
 */
(function () {
  'use strict';

  var root = document.getElementById('sfmApp');
  if (!root || !window.App) { return; }

  var App = window.App;
  var CAN_DELETE = root.getAttribute('data-can-delete') === '1';
  var CAN_UPLOAD = root.getAttribute('data-can-upload') === '1';
  var CAN_MANAGE_SETTINGS = root.getAttribute('data-can-manage-settings') === '1';

  var API_BASE = '/api/v1/storage';
  var VIEW_MODE_KEY = 'sfm-view-mode';

  var ICONS = {
    image: 'bi-image',
    document: 'bi-file-earmark-text',
    video: 'bi-camera-video',
    audio: 'bi-music-note-beamed',
    archive: 'bi-file-earmark-zip',
    other: 'bi-file-earmark'
  };

  var state = {
    search: '',
    type: '',
    visibility: '',
    sort: 'date',
    direction: 'desc',
    trash: false,
    page: 1,
    perPage: 24,
    viewMode: (function () {
      try { return localStorage.getItem(VIEW_MODE_KEY) || 'grid'; } catch (_) { return 'grid'; }
    })(),
    total: 0,
    totalPages: 1,
    limits: null
  };

  /* ---------------------------------------------------------------------
   * Elements
   * ------------------------------------------------------------------- */
  var el = {
    listBody: document.getElementById('sfmListBody'),
    resultCount: document.getElementById('sfmResultCount'),
    pageInfo: document.getElementById('sfmPageInfo'),
    pagination: document.getElementById('sfmPagination'),
    listTitle: document.getElementById('sfmListTitle'),
    search: document.getElementById('sfmSearch'),
    sort: document.getElementById('sfmSort'),
    viewGridBtn: document.getElementById('sfmViewGrid'),
    viewTableBtn: document.getElementById('sfmViewTable'),
    nav: document.getElementById('sfmNav'),
    quickAccess: document.getElementById('sfmQuickAccess'),

    uploadBtn: document.getElementById('sfmUploadBtn'),
    uploadModal: document.getElementById('sfmUploadModal'),
    dropzone: document.getElementById('sfmDropzone'),
    fileInput: document.getElementById('sfmFileInput'),
    uploadList: document.getElementById('sfmUploadList'),
    uploadStartBtn: document.getElementById('sfmUploadStartBtn'),
    uploadConstraints: document.getElementById('sfmUploadConstraints'),
    uploadVisibility: document.getElementById('sfmUploadVisibility'),
    uploadAlert: document.getElementById('sfmUploadAlert'),
    uploadTotalLabel: document.getElementById('sfmUploadTotalProgressLabel'),

    previewModal: document.getElementById('sfmPreviewModal'),
    previewTitle: document.getElementById('sfmPreviewTitle'),
    previewBody: document.getElementById('sfmPreviewBody'),
    previewDownloadBtn: document.getElementById('sfmPreviewDownloadBtn'),
    previewDetailsBtn: document.getElementById('sfmPreviewDetailsBtn'),

    detailsBody: document.getElementById('sfmDetailsBody'),

    deleteModal: document.getElementById('sfmDeleteModal'),
    deleteTitle: document.getElementById('sfmDeleteModalTitle'),
    deleteFileName: document.getElementById('sfmDeleteModalFileName'),
    deleteWarning: document.getElementById('sfmDeleteModalWarning'),
    deleteUsageWarning: document.getElementById('sfmDeleteUsageWarning'),
    deleteConfirmBtn: document.getElementById('sfmDeleteConfirmBtn'),

    settingsBtn: document.getElementById('sfmSettingsBtn'),
    settingsModal: document.getElementById('sfmSettingsModal'),
    settingsForm: document.getElementById('sfmSettingsForm'),
    settingsAlert: document.getElementById('sfmSettingsAlert'),
    settingsSaveBtn: document.getElementById('sfmSettingsSaveBtn')
  };

  var uploadQueue = []; // { file, status, progress, xhr }
  var pendingDeleteUuid = null;
  var pendingDeleteMode = 'trash'; // 'trash' | 'restore' | 'permanent'

  /* ---------------------------------------------------------------------
   * Helpers
   * ------------------------------------------------------------------- */
  function escapeHtml(v) { return App.utils.escapeHtml(v); }
  function formatBytes(v) { return App.utils.formatBytes(v); }
  function formatDate(v) { return App.utils.formatDate(v); }

  function categoryIcon(category) {
    return ICONS[category] || ICONS.other;
  }

  function bsModal(elm) {
    if (!elm || typeof bootstrap === 'undefined') { return null; }
    return bootstrap.Modal.getOrCreateInstance(elm);
  }

  function setNavActive(filterKey, value) {
    if (!el.nav) { return; }
    el.nav.querySelectorAll('[data-sfm-filter]').forEach(function (btn) {
      var match = btn.getAttribute('data-sfm-filter') === filterKey && btn.getAttribute('data-sfm-value') === String(value || '');
      btn.classList.toggle('active', match);
    });
  }

  /* ---------------------------------------------------------------------
   * Data loading
   * ------------------------------------------------------------------- */
  function buildListParams() {
    var params = {
      search: state.search || undefined,
      sort: state.sort,
      direction: state.direction,
      page: state.page,
      per_page: state.perPage
    };
    if (state.trash) {
      params.status = 'pending_delete';
      params.include_pending_deletion = 1;
    } else if (state.type) {
      params.type = state.type;
    }
    if (state.visibility) { params.visibility = state.visibility; }
    return params;
  }

  function loadSummary() {
    App.api.get(API_BASE + '/summary').then(function (res) {
      if (!res.ok || !res.data) { return; }
      var d = res.data;
      state.limits = d.limits || null;
      App.utils.setText(document.querySelector('[data-sfm-stat="totalFiles"]'), d.totalFiles);
      App.utils.setText(document.querySelector('[data-sfm-stat="totalUsed"]'), d.totalFormatted);
      var images = d.byCategory && d.byCategory.image ? d.byCategory.image.count : 0;
      var documents = d.byCategory && d.byCategory.document ? d.byCategory.document.count : 0;
      App.utils.setText(document.querySelector('[data-sfm-stat="images"]'), images);
      App.utils.setText(document.querySelector('[data-sfm-stat="documents"]'), documents);

      if (state.limits && state.limits.maxUploadBytes) {
        var mb = Math.round(state.limits.maxUploadBytes / (1024 * 1024));
        if (el.uploadConstraints) {
          var exts = (state.limits.allowedExtensions || []).join(', ');
          el.uploadConstraints.textContent = 'Max ' + mb + ' MB per file. Allowed: ' + (exts || 'any');
        }
      }
    });
  }

  function loadList() {
    renderLoading();
    var params = buildListParams();
    App.api.get(API_BASE + '/files', params).then(function (res) {
      if (!res.ok) {
        renderError(res.message || 'Failed to load files.');
        return;
      }
      var payload = res.data || {};
      var files = Array.isArray(payload.data) ? payload.data : [];
      var meta = payload.meta || {};
      state.total = meta.total || files.length;
      state.totalPages = meta.totalPages || 1;

      if (files.length === 0) {
        renderEmpty();
      } else if (state.viewMode === 'table') {
        renderTable(files);
      } else {
        renderGrid(files);
      }

      updateResultCount(files.length);
      renderPagination();
    });
  }

  function updateResultCount(shown) {
    if (el.resultCount) {
      el.resultCount.textContent = state.total + ' file' + (state.total === 1 ? '' : 's');
    }
    if (el.pageInfo) {
      if (state.total === 0) {
        el.pageInfo.textContent = '';
      } else {
        var start = (state.page - 1) * state.perPage + 1;
        var end = Math.min(state.page * state.perPage, state.total);
        el.pageInfo.textContent = 'Showing ' + start + '–' + end + ' of ' + state.total;
      }
    }
  }

  /* ---------------------------------------------------------------------
   * Rendering: loading / empty / error
   * ------------------------------------------------------------------- */
  function renderLoading() {
    var skeletons = '';
    for (var i = 0; i < 8; i++) {
      skeletons += '<div class="sfm-card placeholder-glow"><div class="sfm-card-thumb placeholder"></div>' +
        '<div class="sfm-card-body"><span class="placeholder col-8 d-block mb-1"></span><span class="placeholder col-5 d-block"></span></div></div>';
    }
    el.listBody.innerHTML = '<div class="sfm-grid">' + skeletons + '</div>';
    if (el.pagination) { el.pagination.innerHTML = ''; }
  }

  function renderEmpty() {
    var isSearch = !!state.search;
    var isTrash = state.trash;
    var icon = isTrash ? 'bi-trash3' : 'bi-cloud-slash';
    var title = isTrash ? 'Trash is empty' : (isSearch ? 'No files match your search' : 'No files have been uploaded yet.');
    var html = '<div class="sfm-empty"><i class="bi ' + icon + '"></i><p class="fw-medium mb-1">' + escapeHtml(title) + '</p>';
    if (isSearch) {
      html += '<button type="button" class="btn btn-sm btn-outline-secondary" id="sfmClearSearch">Clear filters</button>';
    } else if (CAN_UPLOAD && !isTrash) {
      html += '<button type="button" class="btn btn-sm btn-primary" id="sfmEmptyUploadBtn"><i class="bi bi-cloud-upload me-1"></i>Upload Files</button>';
    }
    html += '</div>';
    el.listBody.innerHTML = html;
    if (el.pagination) { el.pagination.innerHTML = ''; }
    updateResultCount(0);

    var clearBtn = document.getElementById('sfmClearSearch');
    if (clearBtn) { clearBtn.addEventListener('click', clearFilters); }
    var emptyUploadBtn = document.getElementById('sfmEmptyUploadBtn');
    if (emptyUploadBtn) { emptyUploadBtn.addEventListener('click', openUploadModal); }
  }

  function renderError(message) {
    el.listBody.innerHTML = '<div class="sfm-empty"><i class="bi bi-exclamation-triangle text-danger"></i>' +
      '<p class="fw-medium mb-2">' + escapeHtml(message) + '</p>' +
      '<button type="button" class="btn btn-sm btn-outline-secondary" id="sfmRetryBtn">Retry</button></div>';
    if (el.pagination) { el.pagination.innerHTML = ''; }
    var retry = document.getElementById('sfmRetryBtn');
    if (retry) { retry.addEventListener('click', loadList); }
  }

  function clearFilters() {
    state.search = '';
    state.type = '';
    state.visibility = '';
    state.trash = false;
    state.page = 1;
    if (el.search) { el.search.value = ''; }
    setNavActive('type', '');
    if (el.listTitle) { el.listTitle.textContent = 'All Files'; }
    loadList();
  }

  /* ---------------------------------------------------------------------
   * Rendering: grid
   * ------------------------------------------------------------------- */
  function renderGrid(files) {
    var html = '<div class="sfm-grid">' + files.map(renderGridCard).join('') + '</div>';
    el.listBody.innerHTML = html;
    bindCardEvents();
  }

  function renderGridCard(file) {
    var thumb = file.category === 'image' && file.canView
      ? '<img src="' + file.previewUrl + '" alt="" loading="lazy">'
      : '<i class="bi ' + categoryIcon(file.category) + '"></i>';

    var badge = file.visibility === 'public'
      ? '<span class="badge bg-info sfm-card-badge"><i class="bi bi-globe2"></i></span>'
      : '';

    return '' +
      '<div class="sfm-card" data-uuid="' + escapeHtml(file.uuid) + '">' +
      badge +
      '<div class="sfm-card-actions">' + actionDropdown(file) + '</div>' +
      '<div class="sfm-card-thumb" data-role="open">' + thumb + '</div>' +
      '<div class="sfm-card-body" data-role="open">' +
      '<div class="sfm-card-name" title="' + escapeHtml(file.originalName) + '">' + escapeHtml(file.originalName) + '</div>' +
      '<div class="sfm-card-meta"><span>' + escapeHtml(file.sizeFormatted) + '</span><span>' + escapeHtml(formatDate(file.uploadedAt)) + '</span></div>' +
      '</div>' +
      '</div>';
  }

  /* ---------------------------------------------------------------------
   * Rendering: table
   * ------------------------------------------------------------------- */
  function renderTable(files) {
    var rows = files.map(renderTableRow).join('');
    el.listBody.innerHTML = '' +
      '<div class="table-responsive">' +
      '<table class="table table-hover sfm-table mb-0">' +
      '<thead class="table-light"><tr>' +
      '<th>File</th><th class="sfm-col-optional">Type</th><th class="sfm-col-optional">Size</th>' +
      '<th class="sfm-col-optional">Uploaded By</th><th class="sfm-col-optional">Uploaded At</th>' +
      '<th>Status</th><th class="text-end">Actions</th>' +
      '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    bindCardEvents();
  }

  function renderTableRow(file) {
    var thumb = file.category === 'image' && file.canView
      ? '<img src="' + file.previewUrl + '" alt="">'
      : '<i class="bi ' + categoryIcon(file.category) + '"></i>';
    var statusBadge = file.status === 'pending_delete'
      ? '<span class="badge bg-label-danger">In Trash</span>'
      : (file.visibility === 'public' ? '<span class="badge bg-label-info">Public</span>' : '<span class="badge bg-label-secondary">Private</span>');
    var uploader = file.uploadedBy ? escapeHtml(file.uploadedBy.name) : '<span class="text-muted">—</span>';

    return '' +
      '<tr data-uuid="' + escapeHtml(file.uuid) + '">' +
      '<td><div class="sfm-file-cell" data-role="open">' +
      '<div class="sfm-file-icon">' + thumb + '</div>' +
      '<span class="sfm-file-name" title="' + escapeHtml(file.originalName) + '">' + escapeHtml(file.originalName) + '</span>' +
      '</div></td>' +
      '<td class="sfm-col-optional text-uppercase small text-muted">' + escapeHtml(file.extension || file.category) + '</td>' +
      '<td class="sfm-col-optional small">' + escapeHtml(file.sizeFormatted) + '</td>' +
      '<td class="sfm-col-optional small">' + uploader + '</td>' +
      '<td class="sfm-col-optional small">' + escapeHtml(formatDate(file.uploadedAt)) + '</td>' +
      '<td>' + statusBadge + '</td>' +
      '<td class="text-end">' + actionDropdown(file) + '</td>' +
      '</tr>';
  }

  /* ---------------------------------------------------------------------
   * Row / card actions
   * ------------------------------------------------------------------- */
  function actionDropdown(file) {
    var items = '';
    if (file.status === 'pending_delete') {
      if (CAN_DELETE) {
        items += '<li><a href="#" class="dropdown-item" data-action="restore"><i class="bi bi-arrow-counterclockwise me-2 text-muted"></i>Restore</a></li>';
        items += '<li><a href="#" class="dropdown-item text-danger" data-action="permanent"><i class="bi bi-trash3-fill me-2"></i>Delete Permanently</a></li>';
      }
      items += '<li><a href="#" class="dropdown-item" data-action="details"><i class="bi bi-info-circle me-2 text-muted"></i>View details</a></li>';
    } else {
      if (file.canView) {
        items += '<li><a href="#" class="dropdown-item" data-action="preview"><i class="bi bi-eye me-2 text-muted"></i>Preview</a></li>';
      }
      if (file.canDownload) {
        items += '<li><a href="#" class="dropdown-item" data-action="download"><i class="bi bi-download me-2 text-muted"></i>Download</a></li>';
      }
      if (file.canCopyPublicLink) {
        items += '<li><a href="#" class="dropdown-item" data-action="copy-link"><i class="bi bi-link-45deg me-2 text-muted"></i>Copy public link</a></li>';
      }
      items += '<li><a href="#" class="dropdown-item" data-action="copy-uuid"><i class="bi bi-hash me-2 text-muted"></i>Copy reference</a></li>';
      items += '<li><a href="#" class="dropdown-item" data-action="details"><i class="bi bi-info-circle me-2 text-muted"></i>View details</a></li>';
      if (file.canDelete) {
        items += '<li><hr class="dropdown-divider"></li>';
        items += '<li><a href="#" class="dropdown-item text-danger" data-action="trash"><i class="bi bi-trash3 me-2"></i>Move to Trash</a></li>';
      }
    }

    return '' +
      '<div class="dropdown" onclick="event.stopPropagation()">' +
      '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary border-0" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<ul class="dropdown-menu dropdown-menu-end">' + items + '</ul>' +
      '</div>';
  }

  function bindCardEvents() {
    el.listBody.querySelectorAll('[data-uuid]').forEach(function (node) {
      var uuid = node.getAttribute('data-uuid');

      var openTargets = node.querySelectorAll('[data-role="open"]');
      openTargets.forEach(function (target) {
        target.addEventListener('click', function () { openPreview(uuid); });
      });

      node.querySelectorAll('[data-action]').forEach(function (actionLink) {
        actionLink.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          handleAction(actionLink.getAttribute('data-action'), uuid, node);
        });
      });
    });
  }

  function handleAction(action, uuid, node) {
    switch (action) {
      case 'preview': openPreview(uuid); break;
      case 'download': window.location.href = API_BASE + '/files/' + uuid + '/download'; break;
      case 'copy-link': copyToClipboard(window.location.origin + '/api/v1/files/' + uuid + '/view', 'Public link copied.'); break;
      case 'copy-uuid': copyToClipboard(uuid, 'Reference UUID copied.'); break;
      case 'details': openDetails(uuid); break;
      case 'trash': openDeleteConfirm(uuid, node, 'trash'); break;
      case 'restore': confirmRestore(uuid); break;
      case 'permanent': openDeleteConfirm(uuid, node, 'permanent'); break;
      default: break;
    }
  }

  function copyToClipboard(text, successMessage) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        App.notify.success(successMessage);
      }).catch(function () {
        App.notify.error('Unable to copy to clipboard.');
      });
    } else {
      App.notify.error('Clipboard is not available in this browser.');
    }
  }

  /* ---------------------------------------------------------------------
   * Preview
   * ------------------------------------------------------------------- */
  function openPreview(uuid) {
    if (!el.previewModal) { return; }
    bsModal(el.previewModal).show();
    el.previewBody.innerHTML = '<div class="py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading preview…</div>';
    el.previewModal.dataset.uuid = uuid;

    App.api.get(API_BASE + '/files/' + uuid).then(function (res) {
      if (!res.ok || !res.data) {
        el.previewBody.innerHTML = '<div class="py-5 text-danger">' + escapeHtml(res.message || 'Unable to load file.') + '</div>';
        return;
      }
      renderPreview(res.data);
    });
  }

  function renderPreview(file) {
    el.previewTitle.textContent = file.originalName;
    el.previewDownloadBtn.href = file.canDownload ? file.downloadUrl : '#';
    el.previewDownloadBtn.classList.toggle('disabled', !file.canDownload);

    if (!file.canView) {
      el.previewBody.innerHTML = previewFallback(file);
      return;
    }

    if (file.category === 'image') {
      el.previewBody.innerHTML = '<img src="' + file.previewUrl + '" alt="' + escapeHtml(file.originalName) + '" class="img-fluid rounded" style="max-height:60vh">';
    } else if (file.mimeType === 'application/pdf') {
      el.previewBody.innerHTML = '<iframe src="' + file.previewUrl + '" style="width:100%;height:60vh;border:0" title="PDF preview"></iframe>';
    } else if (file.category === 'video') {
      el.previewBody.innerHTML = '<video src="' + file.previewUrl + '" controls style="max-width:100%;max-height:60vh"></video>';
    } else if (file.category === 'audio') {
      el.previewBody.innerHTML = '<audio src="' + file.previewUrl + '" controls style="width:100%"></audio>';
    } else if (file.mimeType === 'text/plain') {
      el.previewBody.innerHTML = '<iframe src="' + file.previewUrl + '" style="width:100%;height:60vh;border:1px solid var(--bs-border-color)" title="Text preview"></iframe>';
    } else {
      el.previewBody.innerHTML = previewFallback(file);
    }
  }

  function previewFallback(file) {
    return '<div class="py-4 text-muted">' +
      '<i class="bi ' + categoryIcon(file.category) + ' display-4 d-block mb-2"></i>' +
      '<p class="mb-1 fw-medium text-dark">' + escapeHtml(file.originalName) + '</p>' +
      '<p class="small mb-0">' + escapeHtml(file.sizeFormatted) + ' · ' + escapeHtml(file.mimeType || 'Unknown type') + '</p>' +
      '<p class="small">This file type cannot be previewed inline.</p>' +
      '</div>';
  }

  if (el.previewDetailsBtn) {
    el.previewDetailsBtn.addEventListener('click', function () {
      var uuid = el.previewModal.dataset.uuid;
      if (uuid) { openDetails(uuid); }
    });
  }

  /* ---------------------------------------------------------------------
   * Details
   * ------------------------------------------------------------------- */
  function openDetails(uuid) {
    var panelEl = document.getElementById('sfmDetailsPanel');
    if (!panelEl || typeof bootstrap === 'undefined') { return; }
    var panel = bootstrap.Offcanvas.getOrCreateInstance(panelEl);
    el.detailsBody.innerHTML = '<div class="py-5 text-center text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
    panel.show();

    App.api.get(API_BASE + '/files/' + uuid).then(function (res) {
      if (!res.ok || !res.data) {
        el.detailsBody.innerHTML = '<div class="text-danger">' + escapeHtml(res.message || 'Unable to load details.') + '</div>';
        return;
      }
      renderDetails(res.data);
    });
  }

  function detailRow(label, value) {
    if (value === null || value === undefined || value === '') { return ''; }
    return '<div class="sfm-detail-row"><dt>' + escapeHtml(label) + '</dt><dd>' + value + '</dd></div>';
  }

  function renderDetails(file) {
    var rows = '';
    rows += detailRow('File name', escapeHtml(file.originalName));
    rows += detailRow('Reference (UUID)', '<code class="small">' + escapeHtml(file.uuid) + '</code>');
    rows += detailRow('MIME type', escapeHtml(file.mimeType || '—'));
    rows += detailRow('Extension', escapeHtml((file.extension || '—').toUpperCase()));
    rows += detailRow('Size', escapeHtml(file.sizeFormatted));
    if (file.width && file.height) {
      rows += detailRow('Dimensions', file.width + ' × ' + file.height + ' px');
    }
    rows += detailRow('Uploaded by', file.uploadedBy ? escapeHtml(file.uploadedBy.name) : 'Unknown');
    rows += detailRow('Uploaded at', escapeHtml(formatDate(file.uploadedAt)));
    if (file.updatedAt) { rows += detailRow('Last updated', escapeHtml(formatDate(file.updatedAt))); }
    rows += detailRow('Visibility', file.visibility === 'public' ? 'Public' : 'Private');
    if (file.checksumSha256) {
      rows += detailRow('Checksum (SHA-256)', '<code class="small" style="word-break:break-all">' + escapeHtml(file.checksumSha256) + '</code>');
    }
    if (typeof file.usageCount === 'number') {
      rows += detailRow('Referenced elsewhere', file.usageCount > 0 ? (file.usageCount + ' record(s)') : 'No');
    }

    var actions = '<div class="d-flex flex-wrap gap-2 mt-3">';
    if (file.canDownload) {
      actions += '<a href="' + file.downloadUrl + '" class="btn btn-sm btn-primary"><i class="bi bi-download me-1"></i>Download</a>';
    }
    if (file.canDelete && file.status !== 'pending_delete') {
      actions += '<button type="button" class="btn btn-sm btn-outline-danger" id="sfmDetailsTrashBtn"><i class="bi bi-trash3 me-1"></i>Move to Trash</button>';
    }
    actions += '</div>';

    el.detailsBody.innerHTML = '<dl class="mb-0">' + rows + '</dl>' + actions;

    var trashBtn = document.getElementById('sfmDetailsTrashBtn');
    if (trashBtn) {
      trashBtn.addEventListener('click', function () {
        openDeleteConfirm(file.uuid, null, 'trash');
      });
    }
  }

  /* ---------------------------------------------------------------------
   * Delete / trash / restore / permanent delete
   * ------------------------------------------------------------------- */
  function openDeleteConfirm(uuid, node, mode) {
    pendingDeleteUuid = uuid;
    pendingDeleteMode = mode;
    el.deleteUsageWarning.classList.add('d-none');
    el.deleteConfirmBtn.disabled = false;

    var name = node ? (node.querySelector('.sfm-card-name, .sfm-file-name') || {}).textContent : '';
    el.deleteFileName.textContent = name || '';

    if (mode === 'trash') {
      el.deleteTitle.textContent = 'Move to trash?';
      el.deleteWarning.textContent = 'This file can be restored from Trash later. Content still using this file may be affected.';
      el.deleteConfirmBtn.textContent = 'Move to Trash';
      bsModal(el.deleteModal).show();
    } else {
      el.deleteTitle.textContent = 'Delete permanently?';
      el.deleteWarning.textContent = 'This cannot be undone.';
      el.deleteConfirmBtn.textContent = 'Delete Permanently';
      el.deleteConfirmBtn.disabled = true;
      bsModal(el.deleteModal).show();

      App.api.get(API_BASE + '/files/' + uuid + '/usage').then(function (res) {
        if (!res.ok || !res.data) { el.deleteConfirmBtn.disabled = false; return; }
        if (res.data.usageCount > 0) {
          el.deleteUsageWarning.textContent = 'This file is still referenced by ' + res.data.usageCount + ' record(s) in other modules and cannot be permanently deleted. Move it to trash instead, or remove the referencing content first.';
          el.deleteUsageWarning.classList.remove('d-none');
          el.deleteConfirmBtn.disabled = true;
        } else {
          el.deleteConfirmBtn.disabled = false;
        }
      });
    }
  }

  function confirmRestore(uuid) {
    App.api.post(API_BASE + '/files/' + uuid + '/restore').then(function (res) {
      if (!res.ok) { App.notify.error(res.message || 'Unable to restore file.'); return; }
      App.notify.success('File restored.');
      loadList();
      loadSummary();
    });
  }

  if (el.deleteConfirmBtn) {
    el.deleteConfirmBtn.addEventListener('click', function () {
      if (!pendingDeleteUuid) { return; }
      var uuid = pendingDeleteUuid;
      var mode = pendingDeleteMode;
      App.ui.setButtonLoading(el.deleteConfirmBtn, true, mode === 'trash' ? 'Moving…' : 'Deleting…');

      var request = mode === 'trash'
        ? App.api.delete(API_BASE + '/files/' + uuid)
        : App.api.delete(API_BASE + '/files/' + uuid + '/permanent');

      request.then(function (res) {
        App.ui.setButtonLoading(el.deleteConfirmBtn, false);
        bsModal(el.deleteModal).hide();
        if (!res.ok) {
          App.notify.error(res.message || 'Unable to complete this action.');
          return;
        }
        App.notify.success(mode === 'trash' ? 'File moved to trash.' : 'File permanently deleted.');
        pendingDeleteUuid = null;
        loadList();
        loadSummary();
      });
    });
  }

  /* ---------------------------------------------------------------------
   * Upload
   * ------------------------------------------------------------------- */
  function openUploadModal() {
    if (!el.uploadModal) { return; }
    bsModal(el.uploadModal).show();
  }

  function resetUploadQueue() {
    uploadQueue = [];
    el.uploadList.innerHTML = '';
    el.uploadStartBtn.disabled = true;
    el.uploadTotalLabel.textContent = '';
    App.ui.clearAlert(el.uploadAlert);
  }

  function addFilesToQueue(fileList) {
    var files = Array.prototype.slice.call(fileList || []);
    if (!files.length) { return; }

    files.forEach(function (file) {
      var entry = { file: file, status: 'pending', progress: 0, id: 'sfmup-' + Math.random().toString(36).slice(2) };
      uploadQueue.push(entry);
      el.uploadList.insertAdjacentHTML('beforeend', renderUploadItem(entry));
    });
    el.uploadStartBtn.disabled = uploadQueue.length === 0;
  }

  function renderUploadItem(entry) {
    return '' +
      '<div class="sfm-upload-item" id="' + entry.id + '">' +
      '<div class="sfm-file-icon"><i class="bi bi-file-earmark"></i></div>' +
      '<div class="sfm-upload-item-info">' +
      '<div class="sfm-upload-item-name">' + escapeHtml(entry.file.name) + '</div>' +
      '<div class="progress"><div class="progress-bar" style="width:0%"></div></div>' +
      '<div class="sfm-upload-item-status">' + escapeHtml(formatBytes(entry.file.size)) + ' · Waiting…</div>' +
      '</div>' +
      '</div>';
  }

  function updateUploadItem(entry, status, progress) {
    var node = document.getElementById(entry.id);
    if (!node) { return; }
    var bar = node.querySelector('.progress-bar');
    var statusEl = node.querySelector('.sfm-upload-item-status');
    if (bar) { bar.style.width = (progress || 0) + '%'; }
    node.classList.remove('is-error', 'is-success');
    if (status === 'uploading') {
      statusEl.textContent = formatBytes(entry.file.size) + ' · Uploading ' + (progress || 0) + '%';
    } else if (status === 'success') {
      node.classList.add('is-success');
      statusEl.textContent = 'Uploaded';
    } else if (status === 'error') {
      node.classList.add('is-error');
      statusEl.textContent = entry.errorMessage || 'Upload failed';
    }
  }

  function uploadOne(entry) {
    return new Promise(function (resolve) {
      var xhr = new XMLHttpRequest();
      entry.xhr = xhr;
      var form = new FormData();
      form.append('file', entry.file);
      form.append('visibility', el.uploadVisibility.value || 'private');

      xhr.open('POST', App.config.baseUrl.replace(/\/$/, '') + API_BASE + '/files');
      xhr.setRequestHeader('X-CSRF-TOKEN', App.utils.getCsrfToken());
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) {
          var pct = Math.round((e.loaded / e.total) * 100);
          entry.progress = pct;
          entry.status = 'uploading';
          updateUploadItem(entry, 'uploading', pct);
          updateTotalProgress();
        }
      });

      xhr.addEventListener('load', function () {
        var ok = xhr.status >= 200 && xhr.status < 300;
        var json = null;
        try { json = JSON.parse(xhr.responseText); } catch (_) { /* noop */ }
        if (ok) {
          entry.status = 'success';
          updateUploadItem(entry, 'success', 100);
        } else {
          entry.status = 'error';
          entry.errorMessage = (json && (json.message || (json.errors && Object.values(json.errors)[0]))) || 'Upload failed';
          updateUploadItem(entry, 'error', 0);
        }
        updateTotalProgress();
        resolve();
      });

      xhr.addEventListener('error', function () {
        entry.status = 'error';
        entry.errorMessage = 'Network error';
        updateUploadItem(entry, 'error', 0);
        updateTotalProgress();
        resolve();
      });

      xhr.send(form);
    });
  }

  function updateTotalProgress() {
    var total = uploadQueue.length;
    if (!total) { return; }
    var doneCount = uploadQueue.filter(function (u) { return u.status === 'success' || u.status === 'error'; }).length;
    var avgProgress = Math.round(uploadQueue.reduce(function (sum, u) { return sum + (u.status === 'success' ? 100 : (u.progress || 0)); }, 0) / total);
    el.uploadTotalLabel.textContent = doneCount + ' of ' + total + ' complete (' + avgProgress + '%)';
  }

  function startUploads() {
    if (!uploadQueue.length) { return; }
    App.ui.setButtonLoading(el.uploadStartBtn, true, 'Uploading…');
    el.fileInput.disabled = true;

    var pending = uploadQueue.filter(function (u) { return u.status === 'pending'; });
    var chain = pending.reduce(function (promise, entry) {
      return promise.then(function () { return uploadOne(entry); });
    }, Promise.resolve());

    chain.then(function () {
      App.ui.setButtonLoading(el.uploadStartBtn, false);
      el.fileInput.disabled = false;
      var failed = uploadQueue.filter(function (u) { return u.status === 'error'; }).length;
      var succeeded = uploadQueue.filter(function (u) { return u.status === 'success'; }).length;

      if (succeeded > 0) {
        App.notify.success(succeeded + ' file(s) uploaded successfully.');
        loadList();
        loadSummary();
      }
      if (failed > 0) {
        App.ui.showAlert('danger', failed + ' file(s) failed to upload.', el.uploadAlert);
      } else {
        setTimeout(function () { bsModal(el.uploadModal).hide(); }, 600);
      }
    });
  }

  /* ---------------------------------------------------------------------
   * Settings
   * ------------------------------------------------------------------- */
  function openSettingsModal() {
    if (!el.settingsModal) { return; }
    bsModal(el.settingsModal).show();
    App.api.get(API_BASE + '/settings').then(function (res) {
      if (!res.ok || !res.data) { return; }
      var values = res.data.values || {};
      var derived = res.data.derived || {};
      var visSelect = document.getElementById('sfmSettingDefaultVisibility');
      var maxUpload = document.getElementById('sfmSettingMaxUpload');
      var extensions = document.getElementById('sfmSettingExtensions');

      if (visSelect) { visSelect.value = values.default_visibility || derived.defaultVisibility || 'private'; }
      if (maxUpload) { maxUpload.value = Math.round((values.max_upload_bytes || derived.maxUploadBytes || 5242880) / (1024 * 1024)); }
      if (extensions) { extensions.value = (values.allowed_extensions || derived.allowedExtensions || []).join(', '); }
    });
  }

  if (el.settingsSaveBtn) {
    el.settingsSaveBtn.addEventListener('click', function () {
      var visSelect = document.getElementById('sfmSettingDefaultVisibility');
      var maxUpload = document.getElementById('sfmSettingMaxUpload');
      var extensions = document.getElementById('sfmSettingExtensions');
      var extList = (extensions.value || '').split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean);

      App.ui.clearAlert(el.settingsAlert);
      App.ui.setButtonLoading(el.settingsSaveBtn, true);

      App.api.put(API_BASE + '/settings', {
        values: {
          default_visibility: visSelect.value,
          max_upload_bytes: Math.max(1, Math.min(50, parseInt(maxUpload.value, 10) || 5)) * 1024 * 1024,
          allowed_extensions: extList
        }
      }).then(function (res) {
        App.ui.setButtonLoading(el.settingsSaveBtn, false);
        if (!res.ok) {
          App.ui.showAlert('danger', res.message || 'Unable to save settings.', el.settingsAlert);
          return;
        }
        App.notify.success('Storage settings updated.');
        bsModal(el.settingsModal).hide();
        loadSummary();
      });
    });
  }

  /* ---------------------------------------------------------------------
   * View mode toggle
   * ------------------------------------------------------------------- */
  function setViewMode(mode) {
    state.viewMode = mode;
    try { localStorage.setItem(VIEW_MODE_KEY, mode); } catch (_) { /* noop */ }
    if (el.viewGridBtn) { el.viewGridBtn.classList.toggle('active', mode === 'grid'); }
    if (el.viewTableBtn) { el.viewTableBtn.classList.toggle('active', mode === 'table'); }
    loadList();
  }

  /* ---------------------------------------------------------------------
   * Pagination
   * ------------------------------------------------------------------- */
  function renderPagination() {
    if (!el.pagination) { return; }
    var pages = state.totalPages;
    if (pages <= 1) { el.pagination.innerHTML = ''; return; }

    var html = '';
    html += '<li class="page-item' + (state.page === 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (state.page - 1) + '">&lsaquo;</a></li>';

    var start = Math.max(1, state.page - 2);
    var end = Math.min(pages, start + 4);
    start = Math.max(1, end - 4);

    for (var p = start; p <= end; p++) {
      html += '<li class="page-item' + (p === state.page ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
    }
    html += '<li class="page-item' + (state.page === pages ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (state.page + 1) + '">&rsaquo;</a></li>';

    el.pagination.innerHTML = html;
    el.pagination.querySelectorAll('.page-link').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var page = parseInt(link.getAttribute('data-page'), 10);
        if (page >= 1 && page <= pages && page !== state.page) {
          state.page = page;
          loadList();
          if (el.listBody.scrollIntoView) { el.listBody.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        }
      });
    });
  }

  /* ---------------------------------------------------------------------
   * Wiring
   * ------------------------------------------------------------------- */
  function init() {
    setViewMode(state.viewMode);

    if (el.search) {
      el.search.addEventListener('input', App.utils.debounce(function () {
        state.search = el.search.value.trim();
        state.page = 1;
        loadList();
      }, 300));
    }

    if (el.sort) {
      el.sort.addEventListener('change', function () {
        var parts = el.sort.value.split(':');
        state.sort = parts[0];
        state.direction = parts[1] || 'desc';
        state.page = 1;
        loadList();
      });
    }

    if (el.viewGridBtn) { el.viewGridBtn.addEventListener('click', function () { setViewMode('grid'); }); }
    if (el.viewTableBtn) { el.viewTableBtn.addEventListener('click', function () { setViewMode('table'); }); }

    document.querySelectorAll('[data-sfm-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var filterKey = btn.getAttribute('data-sfm-filter');
        var value = btn.getAttribute('data-sfm-value') || '';

        state.trash = filterKey === 'trash';
        if (filterKey === 'type') {
          state.type = value;
          state.visibility = '';
        } else if (filterKey === 'visibility') {
          state.visibility = value;
          state.type = '';
        } else if (filterKey === 'sort') {
          var parts = value === 'newest' ? ['date', 'desc'] : ['date', 'asc'];
          state.sort = parts[0];
          state.direction = parts[1];
          if (el.sort) { el.sort.value = parts.join(':'); }
        }
        if (filterKey !== 'trash') { state.trash = false; }
        state.page = 1;

        if (el.listTitle) {
          var titles = { image: 'Images', document: 'Documents', video: 'Videos', audio: 'Audio', archive: 'Archives' };
          if (filterKey === 'trash') { el.listTitle.textContent = 'Trash'; }
          else if (filterKey === 'visibility') { el.listTitle.textContent = 'Public Files'; }
          else if (filterKey === 'type') { el.listTitle.textContent = titles[value] || 'All Files'; }
          else { el.listTitle.textContent = 'Recent Files'; }
        }

        setNavActive(filterKey, value);
        loadList();
      });
    });

    if (CAN_UPLOAD) {
      if (el.uploadBtn) { el.uploadBtn.addEventListener('click', function (e) { e.preventDefault(); openUploadModal(); }); }

      if (el.dropzone) {
        el.dropzone.addEventListener('click', function () { el.fileInput.click(); });
        el.dropzone.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.fileInput.click(); }
        });
        ['dragenter', 'dragover'].forEach(function (evt) {
          el.dropzone.addEventListener(evt, function (e) { e.preventDefault(); el.dropzone.classList.add('sfm-dropzone-active'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
          el.dropzone.addEventListener(evt, function (e) { e.preventDefault(); el.dropzone.classList.remove('sfm-dropzone-active'); });
        });
        el.dropzone.addEventListener('drop', function (e) {
          addFilesToQueue(e.dataTransfer.files);
        });
      }

      if (el.fileInput) {
        el.fileInput.addEventListener('change', function () {
          addFilesToQueue(el.fileInput.files);
          el.fileInput.value = '';
        });
      }

      if (el.uploadStartBtn) { el.uploadStartBtn.addEventListener('click', startUploads); }

      if (el.uploadModal) {
        el.uploadModal.addEventListener('hidden.bs.modal', resetUploadQueue);
      }
    }

    if (CAN_MANAGE_SETTINGS && el.settingsBtn) {
      el.settingsBtn.addEventListener('click', function (e) { e.preventDefault(); openSettingsModal(); });
    }

    loadSummary();
    loadList();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
