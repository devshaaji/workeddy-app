/**
 * Content Editor – Redesigned CMS workspace
 * Three-column: section navigator | main editor | right inspector
 *
 * Dependencies: Quill.js (v1.3.7), Bootstrap 5
 * No frameworks (React, Vue, Alpine, etc.)
 *
 * Architecture: The Quill toolbar, editor container, section heading, and insert
 * actions are static HTML in the PHP view. JS populates and manages them.
 */
(function () {
  'use strict';

  // ── Boot ──────────────────────────────────────────────────────────────

  var root = document.getElementById('contentEditorApp');
  if (!root) return;

  var bootEl = document.getElementById('contentEditorBoot');
  if (!bootEl) return;
  var boot = {};
  try { boot = JSON.parse(bootEl.textContent || '{}'); } catch (e) { /* ok */ }

  var snapshotEl = document.getElementById('contentDraftSnapshot');
  var snapshot = { sections: [], references: [] };
  if (snapshotEl) {
    try { snapshot = JSON.parse(snapshotEl.textContent || '{}'); } catch (e) { /* ok */ }
  }
  if (!Array.isArray(snapshot.sections)) snapshot.sections = [];
  if (!Array.isArray(snapshot.references)) snapshot.references = [];

  var historyEl = document.getElementById('contentRevisionHistory');
  var history = [];
  if (historyEl) {
    try { history = JSON.parse(historyEl.textContent || '[]'); } catch (e) { /* ok */ }
  }
  if (!Array.isArray(history)) history = [];

  var pageApiBaseUrl = boot.pageApiBaseUrl || (boot.pageUuid ? '/api/v1/content/pages/' + boot.pageUuid : '');
  var pageWebBaseUrl = boot.pageWebBaseUrl || (boot.pageUuid ? '/content/pages/' + boot.pageUuid : '');
  var hasDraft = snapshotEl !== null && (snapshot.sections.length > 0 || boot.pageStatus === 'draft');

  // ── API endpoints ─────────────────────────────────────────────────────

  var API = {
    draft: pageApiBaseUrl + '/draft',
    publish: pageApiBaseUrl + '/publish',
    preview: pageApiBaseUrl + '/preview',
    webPreview: pageWebBaseUrl + '/preview',
    archive: pageApiBaseUrl + '/archive',
    restore: pageApiBaseUrl + '/restore',
    revisions: pageApiBaseUrl + '/revisions',
    webRevisions: pageWebBaseUrl + '/revisions',
    media: '/api/v1/content/media',
    storageView: '/api/v1/storage/files',
  };

  // ── State ─────────────────────────────────────────────────────────────

  var state = {
    activeSectionKey: snapshot.sections[0]?.sectionKey || '',
    lockVersion: boot.lockVersion || 0,
    pageStatus: boot.pageStatus || 'draft',
    saveState: 'idle',
    inspectorTab: 'page',
    history: history,
    mediaItems: [],
    selectedMediaUuid: '',
    sectionMenuOpen: null,
    overflowOpen: false,
    isDirty: false,
    isBusy: false,
    validationErrors: {},
    notice: null,
    initialLoadDone: false,
  };

  // ── DOM shortcuts ─────────────────────────────────────────────────────

  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); };

  // ── Quill instance ────────────────────────────────────────────────────

  var quill = null;

  // ── Helpers ───────────────────────────────────────────────────────────

  var escHtml = function (str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  };

  var callApi = async function (method, url, payload, opts) {
    if (!window.App || !window.App.api) {
      throw new Error('App.api is unavailable');
    }
    var verb = String(method || 'GET').toLowerCase();
    var fn = window.App.api[verb];
    if (typeof fn !== 'function') {
      throw new Error('Unsupported API method: ' + method);
    }
    var response = await fn(url, payload, opts || {});
    if (!response || response.ok) {
      return response || { ok: true, data: null, message: '' };
    }
    var err = new Error(response.message || 'Request failed');
    err.status = response.status || 0;
    err.payload = response;
    throw err;
  };

  var debounce = function (fn, ms) {
    var timer;
    return function () {
      var args = arguments;
      var ctx = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  };

  // ── Current section helpers ───────────────────────────────────────────

  var currentSection = function () {
    return snapshot.sections.find(function (s) { return s.sectionKey === state.activeSectionKey; }) || null;
  };

  var sectionIndex = function () {
    return snapshot.sections.findIndex(function (s) { return s.sectionKey === state.activeSectionKey; });
  };

  var sectionCompletion = function (sec) {
    // Check for validation errors that match this section
    var idx = snapshot.sections.indexOf(sec);
    if (idx > -1) {
      var hasErrors = Object.keys(state.validationErrors).some(function (key) {
        return key.indexOf('sections.' + idx) === 0;
      });
      if (hasErrors) return 'error';
    }
    if (!sec) return 'incomplete';
    if (!sec.heading || !sec.heading.trim()) return 'incomplete';
    // Check content delta for meaningful content
    var delta = sec.content && sec.content.delta;
    if (delta && Array.isArray(delta.ops) && delta.ops.length) {
      var hasText = delta.ops.some(function (op) {
        if (typeof op.insert === 'string') return op.insert.replace(/\n/g, '').trim().length > 0;
        if (op.insert && typeof op.insert === 'object') return true; // embeds count as content
        return false;
      });
      if (hasText) return 'complete';
    }
    return 'incomplete';
  };

  var sectionReferences = function () {
    return snapshot.references.filter(function (r) { return r.sectionKey === state.activeSectionKey; });
  };

  // ── Delta sync (store Quill Delta into section) ──────────────────────

  var syncSectionDelta = function () {
    var sec = currentSection();
    if (!sec) return;
    if (quill) {
      sec.content = { format: 'quill_delta', delta: quill.getContents() };
    }
    var headingEl = document.getElementById('ceSectionHeading');
    if (headingEl) sec.heading = headingEl.value;
    state.isDirty = true;
  };

  var setQuillContent = function (html) {
    if (!quill) return;
    html = html || '<p><br></p>';
    var delta = quill.clipboard.convert(html);
    quill.setContents(delta, 'silent');
    quill.history.clear();
  };

  var setQuillDelta = function (delta) {
    if (!quill) return;
    if (!delta || !Array.isArray(delta.ops)) {
      setQuillContent('<p><br></p>');
      return;
    }
    quill.setContents(delta, 'silent');
    quill.history.clear();
  };

  var focusEditor = function () {
    if (quill) setTimeout(function () { quill.focus(); }, 100);
  };

  // ── Quill init ────────────────────────────────────────────────────────

  // ── Custom Quill embeds ──────────────────────────────────────────────

  var registerCustomBlots = function () {
    if (typeof window.Quill === 'undefined') return;
    var BlockEmbed = window.Quill.import('blots/block/embed');

    // ContentImageBlot — renders a visible image card inside the editor
    class ContentImageBlot extends BlockEmbed {
      static create(value) {
        value = value || {};
        var node = super.create();
        node.setAttribute('contenteditable', 'false');
        node.dataset.mediaUuid = value.mediaUuid || '';
        node.dataset.storageFileUuid = value.storageFileUuid || '';
        node.dataset.altText = value.altText || '';
        node.dataset.caption = value.caption || '';
        node.dataset.display = value.display || 'wide';
        node.classList.add('content-image-block');

        var preview = document.createElement('div');
        preview.className = 'content-image-block__preview';
        var img = document.createElement('img');
        img.src = value.previewUrl || '';
        img.alt = value.altText || '';
        img.loading = 'lazy';
        img.addEventListener('error', function () {
          preview.classList.add('is-unavailable');
          preview.innerHTML = '<div class="content-image-block__placeholder"><i class="bi bi-image"></i><span>Image preview unavailable</span></div>';
        });
        preview.appendChild(img);

        var details = document.createElement('div');
        details.className = 'content-image-block__details';
        var title = document.createElement('strong');
        title.textContent = value.caption || 'Image';
        var meta = document.createElement('span');
        meta.textContent = value.altText || 'Alt text not provided';
        details.append(title, meta);
        node.append(preview, details);

        return node;
      }

      static value(node) {
        return {
          mediaUuid: node.dataset.mediaUuid || '',
          storageFileUuid: node.dataset.storageFileUuid || '',
          previewUrl: (node.querySelector('img') || {}).src || '',
          altText: node.dataset.altText || '',
          caption: node.dataset.caption || '',
          display: node.dataset.display || 'wide',
        };
      }
    }

    ContentImageBlot.blotName = 'contentImage';
    ContentImageBlot.tagName = 'figure';
    ContentImageBlot.className = 'content-image-block';

    // ContentReferenceBlot — currently unused for insertion, but keep registration valid.
    class ContentReferenceBlot extends BlockEmbed {
      static create(value) {
        value = value || {};
        var node = super.create();
        node.setAttribute('contenteditable', 'false');
        node.dataset.referenceUuid = value.referenceUuid || '';
        node.dataset.label = value.label || '';
        node.dataset.citation = value.citation || '';
        node.classList.add('content-reference-block');

        var icon = document.createElement('span');
        icon.className = 'content-reference-block__icon';
        icon.innerHTML = '<i class="bi bi-bookmark-fill"></i>';

        var content = document.createElement('span');
        content.className = 'content-reference-block__content';
        var label = document.createElement('strong');
        label.textContent = value.label || 'Reference';
        var citation = document.createElement('small');
        citation.textContent = value.citation || 'Citation details not provided';
        content.append(label, citation);

        node.append(icon, content);
        return node;
      }

      static value(node) {
        return {
          referenceUuid: node.dataset.referenceUuid || '',
          label: node.dataset.label || '',
          citation: node.dataset.citation || (node.querySelector('small') || {}).textContent || '',
        };
      }
    }

    ContentReferenceBlot.blotName = 'contentReference';
    ContentReferenceBlot.tagName = 'aside';
    ContentReferenceBlot.className = 'content-reference-block';

    window.Quill.register(ContentImageBlot);
    window.Quill.register(ContentReferenceBlot);
  };

  var initQuill = function () {
    if (typeof window.Quill === 'undefined') {
      console.warn('Quill not loaded');
      return;
    }
    var editorEl = document.getElementById('ceQuillEditor');
    if (!editorEl) return;

    // Register custom embeds before creating Quill instance
    registerCustomBlots();

    quill = new window.Quill('#ceQuillEditor', {
      theme: 'snow',
      modules: {
        toolbar: {
          container: '#ceQuillToolbar',
          handlers: {
            undo: function () { if (quill) quill.history.undo(); },
            redo: function () { if (quill) quill.history.redo(); },
          },
        },
        history: {
          delay: 400,
          maxStack: 100,
          userOnly: true,
        },
      },
      formats: [
        'header',
        'bold',
        'italic',
        'list',
        'bullet',
        'blockquote',
        'link',
        'clean',
        'contentImage',
        'contentReference',
      ],
      placeholder: 'Start writing\u2026',
    });

    quill.on('text-change', function () {
      state.isDirty = true;
    });
  };

  // Insert embed helpers
  var insertContentImage = function (imageData) {
    if (!quill) return;
    var range = quill.getSelection(true);
    var index = range ? range.index : quill.getLength();
    quill.insertEmbed(index, 'contentImage', {
      mediaUuid: imageData.uuid,
      storageFileUuid: imageData.storageFileUuid || '',
      previewUrl: imageData.previewUrl || (API.storageView + '/' + (imageData.storageFileUuid || '') + '/view'),
      altText: imageData.altText || imageData.defaultAltText || '',
      caption: imageData.caption || imageData.defaultCaption || '',
      display: imageData.display || 'wide',
    }, window.Quill.sources.USER);
    quill.insertText(index + 1, '\n', window.Quill.sources.SILENT);
    quill.setSelection(index + 2, 0, window.Quill.sources.SILENT);
    state.isDirty = true;
  };

  var insertContentReference = function (refData) {
    if (!quill) return;
    var range = quill.getSelection(true);
    var index = range ? range.index : quill.getLength();
    quill.insertEmbed(index, 'contentReference', {
      referenceUuid: refData._key || refData.referenceUuid || '',
      label: refData.title || 'Reference',
      citation: refData.citation || '',
    }, window.Quill.sources.USER);
    quill.insertText(index + 1, '\n', window.Quill.sources.SILENT);
    quill.setSelection(index + 2, 0, window.Quill.sources.SILENT);
    state.isDirty = true;
  };

  var insertReferenceMarker = function (refData) {
    if (!quill) return;
    var range = quill.getSelection(true);
    var index = range ? range.index : quill.getLength();
    var title = (refData && refData.title ? String(refData.title).trim() : '') || 'Reference';
    var marker = '[Ref: ' + title + '] ';
    quill.insertText(index, marker, { italic: true }, window.Quill.sources.USER);
    quill.setSelection(index + marker.length, 0, window.Quill.sources.SILENT);
    state.isDirty = true;
  };

  // Convert legacy section format (blocks[]) to new format (content.delta)
  var ensureSectionContent = function (sec) {
    if (!sec) return;
    if (sec.content && sec.content.format === 'quill_delta') return;
    if (Array.isArray(sec.blocks) && sec.blocks.length) {
      if (!quill) {
        sec.content = { format: 'quill_delta', delta: { ops: [{ insert: '\n' }] } };
        return;
      }
      var htmlParts = [];
      var extras = [];
      sec.blocks.forEach(function (b) {
        if (b.type === 'rich_text') htmlParts.push(b.body || '');
        else if (b.type === 'paragraph') htmlParts.push('<p>' + (b.text || '') + '</p>');
        else if (b.type === 'image') extras.push(b);
      });
      var delta = quill.clipboard.convert(htmlParts.join('') || '<p><br></p>');
      var ops = delta.ops ? delta.ops.slice() : [];
      extras.forEach(function (img) {
        ops.push({ insert: { contentImage: { mediaUuid: img.mediaUuid || '', previewUrl: '', altText: img.altText || '', caption: img.caption || '', display: img.display || 'wide' } } });
        ops.push({ insert: '\n' });
      });
      sec.content = { format: 'quill_delta', delta: { ops: ops } };
      delete sec.blocks;
    } else {
      sec.content = { format: 'quill_delta', delta: { ops: [{ insert: '\n' }] } };
      if (sec.blocks) delete sec.blocks;
    }
  };

  // ── Save / Publish ────────────────────────────────────────────────────

  var setBusy = function (busy, label) {
    state.isBusy = busy;
    if (label) setNotice(label, 'info');
    $$('[data-ce-action]').forEach(function (btn) {
      if (btn.dataset.ceAction || btn.id.indexOf('ceSave') > -1 || btn.id.indexOf('cePublish') > -1 || btn.id.indexOf('ceAddSection') > -1) {
        btn.disabled = busy;
      }
    });
  };

  var setNotice = function (msg, type) {
    var el = document.getElementById('ceNotice');
    if (!el) return;
    if (!msg) { el.style.display = 'none'; return; }
    el.style.display = 'flex';
    el.className = 'ce-notice ce-notice--' + (type || 'info');
    el.innerHTML = '<span>' + escHtml(msg) + '</span><button class="ce-notice__close" onclick="this.parentElement.style.display=\'none\'">&times;</button>';
  };

  var setSaveState = function (s) {
    state.saveState = s;
    var el = document.getElementById('ceSaveState');
    if (!el) return;
    var dot = el.querySelector('.ce-save-state__dot');
    var label = el.querySelector('.ce-save-state__label');
    if (!label) return;
    el.className = 'ce-save-state';
    switch (s) {
      case 'saving':
        el.classList.add('ce-save-state--saving');
        label.textContent = 'Saving\u2026';
        if (dot) dot.style.display = 'inline-block';
        break;
      case 'saved':
        el.classList.add('ce-save-state--saved');
        label.textContent = 'Saved';
        if (dot) dot.style.display = 'inline-block';
        break;
      case 'error':
        el.classList.add('ce-save-state--error');
        label.textContent = 'Save failed';
        if (dot) dot.style.display = 'inline-block';
        break;
      case 'stale':
        el.classList.add('ce-save-state--error');
        label.textContent = 'Stale';
        if (dot) dot.style.display = 'inline-block';
        break;
      default:
        label.textContent = '';
        if (dot) dot.style.display = 'none';
    }
  };

  var parseValidationErrors = function (err) {
    // Extract field-level errors from the API error payload
    var errors = {};
    if (err.payload && err.payload.errors && typeof err.payload.errors === 'object') {
      var raw = err.payload.errors;
      Object.keys(raw).forEach(function (key) {
        if (typeof raw[key] === 'string') {
          errors[key] = raw[key];
        }
      });
    } else if (err.payload && err.payload.message) {
      // Single validation message, treat as general (not section-specific)
      if (err.status === 422) {
        errors['_general'] = err.payload.message;
      }
    }
    return errors;
  };

  var saveDraft = async function () {
    if (state.isBusy) return;
    setBusy(true, 'Saving draft\u2026');
    setSaveState('saving');
    try {
      // Convert all sections to delta format before saving
      snapshot.sections.forEach(function (sec) {
        if (sec.content && sec.content.format === 'quill_delta') return;
        ensureSectionContent(sec);
      });
      syncSectionDelta();
      var titleEl = document.getElementById('ceHeaderTitle');
      var seoTitleEl = document.getElementById('ceInspectorSeoTitle');
      var seoDescEl = document.getElementById('ceInspectorSeoDescription');
      var data = await callApi('PUT', API.draft, {
        title: titleEl ? titleEl.value || boot.pageTitle || 'Content Page' : boot.pageTitle || 'Content Page',
        seoTitle: seoTitleEl ? seoTitleEl.value || null : null,
        seoDescription: seoDescEl ? seoDescEl.value || null : null,
        snapshot: JSON.parse(JSON.stringify(snapshot)),
        expectedLockVersion: state.lockVersion,
        changeSummary: 'Edited from content workspace',
      });
      // Clear validation errors on success
      state.validationErrors = {};
      setSaveState('saved');
      setNotice(data.message || 'Draft saved.', 'success');
      state.isDirty = false;
      renderSectionNav();
      renderSectionTab();
      await loadEditorState();
    } catch (err) {
      if (err.status === 409 || (err.message || '').indexOf('lock version is stale') > -1) {
        setSaveState('stale');
        setNotice('Draft save rejected: lock version is stale. Latest draft reloaded.', 'warning');
        await loadEditorState();
        return;
      }
      if (err.status === 422) {
        state.validationErrors = parseValidationErrors(err);
        setSaveState('error');
        setNotice('Validation failed. Check the Section inspector tab for details.', 'warning');
        renderSectionNav();
        renderSectionTab();
        return;
      }
      setSaveState('error');
      setNotice(err.message || 'Draft save failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  var publishDraft = async function () {
    if (state.isBusy) return;
    setBusy(true, 'Publishing\u2026');
    try {
      // Convert all sections to delta format before publishing
      snapshot.sections.forEach(function (sec) {
        if (sec.content && sec.content.format === 'quill_delta') return;
        ensureSectionContent(sec);
      });
      syncSectionDelta();
      var data = await callApi('POST', API.publish, {
        changeSummary: 'Published from content workspace',
      });
      state.validationErrors = {};
      setNotice(data.message || 'Content published.', 'success');
      state.pageStatus = 'published';
      renderHeaderStatus();
      await loadEditorState();
    } catch (err) {
      if (err.status === 422) {
        state.validationErrors = parseValidationErrors(err);
        setNotice('Validation failed. Fix errors before publishing.', 'warning');
        renderSectionNav();
        renderSectionTab();
        return;
      }
      setNotice(err.message || 'Publish failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  var archivePage = async function () {
    if (state.isBusy) return;
    setBusy(true, 'Archiving\u2026');
    try {
      await callApi('POST', API.archive, {});
      setNotice('Page archived.', 'success');
      state.pageStatus = 'archived';
      renderHeaderStatus();
      await loadEditorState();
    } catch (err) {
      setNotice(err.message || 'Archive failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  var restoreRevision = async function (revisionUuid) {
    if (state.isBusy || !revisionUuid) return;
    setBusy(true, 'Restoring\u2026');
    try {
      var data = await callApi('POST', API.restore + '/' + revisionUuid + '/restore', {
        changeSummary: 'Restore revision ' + revisionUuid,
      });
      setNotice(data.message || 'Revision restored.', 'success');
      await loadEditorState();
    } catch (err) {
      setNotice(err.message || 'Restore failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  // ── Data loading ──────────────────────────────────────────────────────

  var revealWorkspace = function () {
    var skeleton = document.getElementById('ceSkeleton');
    var content = document.getElementById('ceWorkspaceContent');
    if (skeleton) skeleton.classList.add('ce-skeleton--hidden');
    if (content) content.style.display = '';
  };

  var loadEditorState = async function () {
    try {
      var results = await Promise.all([
        callApi('GET', API.draft),
        callApi('GET', API.revisions),
      ]);
      state.history = Array.isArray(results[1].data?.history) ? results[1].data.history : [];
      var draft = results[0].data?.draft || null;
      if (draft?.snapshot) {
        snapshot = JSON.parse(JSON.stringify(draft.snapshot));
        if (!Array.isArray(snapshot.sections)) snapshot.sections = [];
        if (!Array.isArray(snapshot.references)) snapshot.references = [];
        state.lockVersion = Number(draft.lockVersion || state.lockVersion || 0);
        if (!snapshot.sections.some(function (s) { return s.sectionKey === state.activeSectionKey; })) {
          state.activeSectionKey = snapshot.sections[0]?.sectionKey || '';
        }
      }
      state.initialLoadDone = true;
      // Clear validation errors on fresh data load (draft was saved successfully server-side)
      state.validationErrors = {};
      renderAll();
      revealWorkspace();
    } catch (err) {
      setNotice(err.message || 'Failed to load editor.', 'error');
      revealWorkspace();
    }
  };

  var loadMedia = async function () {
    try {
      var res = await callApi('GET', API.media);
      state.mediaItems = Array.isArray(res.data?.media) ? res.data.media : [];
      renderMediaTab();
      renderMediaModal();
    } catch (_) { /* silent */ }
  };

  // ── Section operations ────────────────────────────────────────────────

  var addSection = function () {
    var key = 'section_' + Date.now();
    snapshot.sections.push({
      sectionKey: key,
      heading: 'New section',
      displayOrder: snapshot.sections.length + 1,
      content: { format: 'quill_delta', delta: { ops: [{ insert: '\n' }] } },
    });
    state.activeSectionKey = key;
    state.isDirty = true;
    renderSectionNav();
    renderEditorForCurrentSection();
    renderSectionTab();
    renderReferencesTab();
    focusEditor();
  };

  var selectSection = function (key) {
    if (!key) return;
    syncSectionDelta();
    state.activeSectionKey = key;
    state.sectionMenuOpen = null;
    renderSectionNav();
    renderEditorForCurrentSection();
    renderSectionTab();
    focusEditor();
  };

  var removeSection = function (key) {
    var idx = snapshot.sections.findIndex(function (s) { return s.sectionKey === key; });
    if (idx === -1) return;
    if (snapshot.sections.length <= 1) {
      setNotice('Cannot remove the last section.', 'warning');
      return;
    }
    snapshot.sections.splice(idx, 1);
    if (state.activeSectionKey === key) {
      state.activeSectionKey = snapshot.sections[Math.min(idx, snapshot.sections.length - 1)]?.sectionKey || '';
    }
    state.isDirty = true;
    renderSectionNav();
    renderEditorForCurrentSection();
    renderSectionTab();
  };

  var moveSection = function (key, dir) {
    var idx = snapshot.sections.findIndex(function (s) { return s.sectionKey === key; });
    if (idx === -1) return;
    var newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= snapshot.sections.length) return;
    var tmp = snapshot.sections[idx];
    snapshot.sections[idx] = snapshot.sections[newIdx];
    snapshot.sections[newIdx] = tmp;
    state.isDirty = true;
    renderSectionNav();
    renderSectionTab();
  };

  // ── References ────────────────────────────────────────────────────────

  var openRefModal = function (ref) {
    var modal = new bootstrap.Modal(document.getElementById('ceReferenceModal'));
    var form = document.getElementById('ceReferenceForm');
    if (form) form.reset();
    var title = document.getElementById('ceReferenceModalTitle');
    var sec = currentSection();
    if (ref) {
      title.textContent = 'Edit reference';
      document.getElementById('ceReferenceKey').value = ref._key || '';
      document.getElementById('ceRefTitle').value = ref.title || '';
      document.getElementById('ceRefAuthor').value = ref.author || '';
      document.getElementById('ceRefYear').value = ref.year || '';
      document.getElementById('ceRefUrl').value = ref.url || '';
      document.getElementById('ceRefCitation').value = ref.citation || '';
      document.getElementById('ceRefSectionKey').value = ref.sectionKey || '';
      document.getElementById('ceRefSectionKeyLabel').textContent = 'Section: ' + (snapshot.sections.find(function (s) { return s.sectionKey === ref.sectionKey; })?.heading || ref.sectionKey || '');
    } else {
      title.textContent = 'Add reference';
      document.getElementById('ceReferenceKey').value = '';
      document.getElementById('ceRefSectionKey').value = state.activeSectionKey;
      document.getElementById('ceRefSectionKeyLabel').textContent = 'Section: ' + (sec?.heading || state.activeSectionKey);
    }
    modal.show();
  };

  var saveReference = function () {
    var key = document.getElementById('ceReferenceKey').value;
    var data = {
      sectionKey: document.getElementById('ceRefSectionKey').value,
      title: document.getElementById('ceRefTitle').value,
      author: document.getElementById('ceRefAuthor').value,
      year: document.getElementById('ceRefYear').value,
      url: document.getElementById('ceRefUrl').value,
      citation: document.getElementById('ceRefCitation').value,
      displayOrder: 1,
    };
    if (key) {
      var idx = snapshot.references.findIndex(function (r) { return r._key === key; });
      if (idx > -1) Object.assign(snapshot.references[idx], data);
    } else {
      data._key = 'ref_' + Date.now();
      data.displayOrder = snapshot.references.filter(function (r) { return r.sectionKey === data.sectionKey; }).length + 1;
      snapshot.references.push(data);
    }
    state.isDirty = true;
    bootstrap.Modal.getInstance(document.getElementById('ceReferenceModal'))?.hide();
    // Insert a simple inline marker; the canonical citation is stored in snapshot.references.
    if (!key) {
      insertReferenceMarker(data);
    }
    renderReferencesTab();
  };

  var removeReference = function (key) {
    snapshot.references = snapshot.references.filter(function (r) { return r._key !== key; });
    state.isDirty = true;
    renderReferencesTab();
  };

  // ── Media ─────────────────────────────────────────────────────────────

  var openMediaModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('ceMediaModal'));
    loadMedia();
    modal.show();
  };

  var selectMedia = function (media) {
    state.selectedMediaUuid = media.uuid;
    renderMediaModal();
    var detail = document.getElementById('ceMediaDetail');
    if (!detail) return;
    var isImage = media.mimeType && media.mimeType.indexOf('image/') === 0;
    detail.innerHTML = isImage
      ? '<img class="ce-media-modal__preview" src="' + API.storageView + '/' + (media.storageFileUuid || '') + '/view" alt="" loading="lazy">'
      : '<div class="ce-media-modal__preview d-flex align-items-center justify-content-center text-muted" style="height:120px"><i class="bi bi-file-earmark" style="font-size:2rem"></i></div>';
    detail.innerHTML += '<div class="mb-2"><label class="form-label">Alt text</label><input class="form-control form-control-sm" id="ceMediaAltText" value="' + escHtml(media.defaultAltText || '') + '" placeholder="Describe the image"></div>';
    detail.innerHTML += '<div class="mb-2"><label class="form-label">Caption</label><textarea class="form-control" id="ceMediaCaption" rows="2" placeholder="Optional caption">' + escHtml(media.defaultCaption || '') + '</textarea></div>';
    detail.innerHTML += '<div class="mb-2"><label class="form-label">Display</label><select class="form-select form-select-sm" id="ceMediaDisplay"><option value="inline">Inline</option><option value="wide" selected>Wide</option><option value="full-width">Full width</option><option value="left">Left</option><option value="right">Right</option></select></div>';
    detail.innerHTML += '<button class="btn btn-primary btn-sm mt-2" id="ceMediaUseBtn"><i class="bi bi-check-lg"></i> Use image</button>';
    document.getElementById('ceMediaUseBtn')?.addEventListener('click', function () { useMedia(media); }, { once: true });
    // Also enable footer button
    var useBtn = document.getElementById('ceMediaUseImage');
    if (useBtn) { useBtn.disabled = false; useBtn.onclick = function () { useMedia(media); }; }
  };

  var useMedia = function (media) {
    var sec = currentSection();
    if (!sec) return;
    var altEl = document.getElementById('ceMediaAltText');
    var capEl = document.getElementById('ceMediaCaption');
    var dispEl = document.getElementById('ceMediaDisplay');
    var imageData = {
      uuid: media.uuid,
      storageFileUuid: media.storageFileUuid || '',
      previewUrl: API.storageView + '/' + (media.storageFileUuid || '') + '/view',
      altText: altEl ? altEl.value : (media.defaultAltText || ''),
      caption: capEl ? capEl.value : (media.defaultCaption || ''),
      display: dispEl ? dispEl.value : 'wide',
    };
    // Insert the image as a custom Quill embed at cursor position
    insertContentImage(imageData);
    bootstrap.Modal.getInstance(document.getElementById('ceMediaModal'))?.hide();
    renderSectionNav();
    renderMediaTab();
  };

  var uploadMedia = async function (file) {
    if (!file) return;
    setBusy(true, 'Uploading\u2026');
    try {
      var fd = new FormData();
      fd.append('file', file);
      fd.append('defaultAltText', file.name.replace(/\.[^.]+$/, ''));
      await callApi('POST', API.media, fd);
      await loadMedia();
      setNotice('Media uploaded.', 'success');
    } catch (err) {
      setNotice(err.message || 'Upload failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  // ── Navigation menu ───────────────────────────────────────────────────

  var toggleSectionMenu = function (key, anchor) {
    if (state.sectionMenuOpen === key) {
      state.sectionMenuOpen = null;
      removeSectionMenu();
      return;
    }
    state.sectionMenuOpen = key;
    removeSectionMenu();
    var menu = document.createElement('div');
    menu.className = 'ce-overflow-menu ce-overflow-menu--open';
    menu.id = 'ceSectionMenu';
    menu.style.position = 'fixed';
    var rect = anchor.getBoundingClientRect();
    menu.style.top = (rect.bottom + 4) + 'px';
    menu.style.left = (rect.left - 160) + 'px';
    menu.style.zIndex = '300';
    menu.innerHTML =
      '<button class="ce-overflow-menu__item" data-section-move-up="' + key + '"><i class="bi bi-chevron-up"></i> Move up</button>' +
      '<button class="ce-overflow-menu__item" data-section-move-down="' + key + '"><i class="bi bi-chevron-down"></i> Move down</button>' +
      '<div class="ce-overflow-menu__divider"></div>' +
      '<button class="ce-overflow-menu__item ce-overflow-menu__item--danger" data-section-remove="' + key + '"><i class="bi bi-trash"></i> Remove section</button>';
    document.body.appendChild(menu);
    var backdrop = document.createElement('div');
    backdrop.className = 'ce-overflow-menu__backdrop';
    backdrop.id = 'ceSectionMenuBackdrop';
    backdrop.addEventListener('click', function () { state.sectionMenuOpen = null; removeSectionMenu(); });
    document.body.appendChild(backdrop);
    menu.querySelector('[data-section-move-up]')?.addEventListener('click', function () { moveSection(key, -1); removeSectionMenu(); });
    menu.querySelector('[data-section-move-down]')?.addEventListener('click', function () { moveSection(key, 1); removeSectionMenu(); });
    menu.querySelector('[data-section-remove]')?.addEventListener('click', function () { removeSection(key); removeSectionMenu(); });
  };

  var removeSectionMenu = function () {
    var m = document.getElementById('ceSectionMenu'); if (m) m.remove();
    var b = document.getElementById('ceSectionMenuBackdrop'); if (b) b.remove();
  };

  // ── Rendering ─────────────────────────────────────────────────────────

  var renderAll = function () {
    renderHeaderStatus();
    renderSectionNav();
    renderEditorForCurrentSection();
    renderInspector();
    renderPageTab();
    renderSectionTab();
    renderReferencesTab();
    renderHistoryTab();
    renderMediaTab();
  };

  var renderHeaderStatus = function () {
    var badge = document.getElementById('ceStatusBadge');
    if (!badge) return;
    var label = state.pageStatus || 'draft';
    badge.className = 'ce-status-badge ce-status-badge--' + label;
    badge.textContent = label;
  };

  var renderSectionNav = function () {
    var list = document.getElementById('ceSectionList');
    if (!list) return;
    list.innerHTML = '';
    if (!snapshot.sections.length) {
      list.innerHTML = '<div class="ce-empty-state"><div class="ce-empty-state__text">No sections yet</div></div>';
      return;
    }
    snapshot.sections.forEach(function (sec, idx) {
      var sel = sec.sectionKey === state.activeSectionKey;
      var comp = sectionCompletion(sec);
      var item = document.createElement('div');
      item.className = 'ce-nav__item' + (sel ? ' ce-nav__item--selected' : '');
      item.innerHTML =
        '<div class="ce-nav__number">' + (idx + 1) + '</div>' +
        '<div class="ce-nav__label">' + escHtml(sec.heading || sec.sectionKey) + '</div>' +
        '<div class="ce-nav__status"><span class="ce-nav__status-icon ce-nav__status-icon--' + comp + '"></span></div>' +
        '<div class="ce-nav__actions"><button class="ce-nav__action-btn" data-section-menu="' + sec.sectionKey + '" title="Section actions"><i class="bi bi-three-dots-vertical"></i></button></div>';
      item.addEventListener('click', function (e) {
        if (e.target.closest('[data-section-menu]')) return;
        selectSection(sec.sectionKey);
      });
      list.appendChild(item);
    });
    // Bind action menus
    $$('[data-section-menu]', list).forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleSectionMenu(this.dataset.sectionMenu, this);
      });
    });
  };

  var renderEditorForCurrentSection = function () {
    var sec = currentSection();
    var numEl = document.getElementById('ceSectionNumber');
    var headingEl = document.getElementById('ceSectionHeading');
    var descEl = document.getElementById('ceSectionDescription');
    if (!numEl || !headingEl) return;
    if (!sec) {
      numEl.textContent = 'No section selected';
      headingEl.value = '';
      if (descEl) descEl.textContent = '';
      setQuillContent('<p><br></p>');
      return;
    }
    var idx = sectionIndex();
    numEl.textContent = 'Section ' + (idx + 1);
    headingEl.value = sec.heading || '';
    if (descEl) descEl.textContent = sec.description || '';
    // Ensure section has content in the new format
    ensureSectionContent(sec);
    setQuillDelta(sec.content.delta);
  };

  // ── Inspector ─────────────────────────────────────────────────────────

  var renderInspector = function () {
    $$('.ce-inspector__tab').forEach(function (tab) {
      tab.classList.toggle('ce-inspector__tab--active', tab.dataset.inspectorTab === state.inspectorTab);
    });
    $$('.ce-inspector__panel').forEach(function (p) {
      var panelId = 'ceInspector' + (p.dataset.inspectorPanel || p.id.replace('ceInspector', ''));
      p.classList.toggle('ce-inspector__panel--active', p.id === ('ceInspector' + state.inspectorTab.charAt(0).toUpperCase() + state.inspectorTab.slice(1)));
    });
  };

  var renderPageTab = function () {
    var el = document.getElementById('ceInspectorPage');
    if (!el) return;
    el.innerHTML =
      '<div class="ce-inspector__field-group"><label class="form-label">Page title</label><div class="ce-inspector__field-value">' + escHtml(boot.pageTitle || '') + '</div></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Page key</label><div class="ce-inspector__field-value"><code>' + escHtml(boot.pageKey || '') + '</code></div></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Audience</label><div class="ce-inspector__field-value">' + escHtml(boot.audience || 'internal') + '</div></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Status</label><span class="ce-status-badge ce-status-badge--' + state.pageStatus + '">' + state.pageStatus + '</span></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Template</label><div class="ce-inspector__field-value">' + escHtml(boot.templateKey || 'internal_default') + '</div></div>' +
      '<hr class="my-3">' +
      '<div class="ce-inspector__field-group"><label class="form-label">SEO title</label><input class="form-control form-control-sm" id="ceInspectorSeoTitle" value="' + escHtml(boot.seoTitle || '') + '" placeholder="SEO title"></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">SEO description</label><textarea class="form-control" id="ceInspectorSeoDescription" rows="3" placeholder="SEO meta description">' + escHtml(boot.seoDescription || '') + '</textarea></div>';
    ['ceInspectorSeoTitle', 'ceInspectorSeoDescription'].forEach(function (id) {
      document.getElementById(id)?.addEventListener('input', function () { state.isDirty = true; });
    });
  };

  var renderSectionTab = function () {
    var el = document.getElementById('ceInspectorSection');
    if (!el) return;
    var sec = currentSection();
    if (!sec) {
      el.innerHTML = '<div class="ce-empty-state"><div class="ce-empty-state__text">No section selected</div></div>';
      return;
    }
    var idx = sectionIndex();
    // Collect validation errors for this section
    var sectionErrors = [];
    if (idx > -1) {
      Object.keys(state.validationErrors).forEach(function (key) {
        if (key.indexOf('sections.' + idx) === 0) {
          sectionErrors.push(state.validationErrors[key]);
        }
      });
    }
    var errorsHtml = '';
    if (sectionErrors.length) {
      errorsHtml = sectionErrors.map(function (msg) {
        return '<div class="ce-validation-msg"><i class="bi bi-exclamation-triangle"></i> ' + escHtml(msg) + '</div>';
      }).join('');
    } else {
      errorsHtml = '<div class="text-muted small">No errors</div>';
    }
    el.innerHTML =
      '<div class="ce-inspector__field-group"><label class="form-label">Section heading</label><div class="ce-inspector__field-value">' + escHtml(sec.heading || '') + '</div></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Section key</label><div class="ce-inspector__field-value"><code>' + escHtml(sec.sectionKey || '') + '</code></div></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Visibility</label><select class="form-select form-select-sm" id="ceSectionVisibility"><option value="visible">Visible</option><option value="hidden">Hidden</option></select></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Display type</label><select class="form-select form-select-sm" id="ceSectionDisplayType"><option value="standard">Standard</option><option value="wide">Wide</option><option value="compact">Compact</option></select></div>' +
      '<div class="ce-inspector__field-group"><label class="form-label">Validation</label>' + errorsHtml + '</div>';
  };

  var renderReferencesTab = function () {
    var el = document.getElementById('ceInspectorReferences');
    if (!el) return;
    if (!snapshot.references.length) {
      el.innerHTML = '<div class="ce-empty-state"><div class="ce-empty-state__icon"><i class="bi bi-bookmark"></i></div><div class="ce-empty-state__text">No references yet</div></div>' +
        '<button class="btn btn-primary btn-sm mt-2" id="ceAddRefTab" type="button"><i class="bi bi-plus-lg"></i> Add reference</button>';
      document.getElementById('ceAddRefTab')?.addEventListener('click', function () { openRefModal(null); });
      return;
    }
    var html = '';
    snapshot.sections.forEach(function (sec) {
      var refs = snapshot.references.filter(function (r) { return r.sectionKey === sec.sectionKey; });
      if (!refs.length) return;
      html += '<div class="fw-semibold small mt-2 mb-1" style="color:var(--ce-text)">' + escHtml(sec.heading || sec.sectionKey) + '</div>';
      refs.forEach(function (r) {
        html += '<div class="ce-ref-item"><div class="ce-ref-item__title">' + escHtml(r.title || 'Untitled') + '</div>' +
          '<div class="ce-ref-item__meta">' + escHtml(r.author || '') + (r.year ? ' (' + escHtml(r.year) + ')' : '') + '</div>' +
          '<div class="ce-ref-item__actions">' +
          '<button class="ce-ref-item__action" data-ref-edit-tab="' + r._key + '"><i class="bi bi-pencil"></i></button>' +
          '<button class="ce-ref-item__action ce-ref-item__action--remove" data-ref-remove-tab="' + r._key + '"><i class="bi bi-trash"></i></button>' +
          '</div></div>';
      });
    });
    html += '<button class="btn btn-primary btn-sm mt-2" id="ceAddRefTab" type="button"><i class="bi bi-plus-lg"></i> Add reference</button>';
    el.innerHTML = html;
    el.querySelectorAll('[data-ref-edit-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var refKey = this.dataset.refEditTab;
        var ref = snapshot.references.find(function (r) { return r._key === refKey; });
        if (ref) openRefModal(ref);
      });
    });
    el.querySelectorAll('[data-ref-remove-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () { removeReference(this.dataset.refRemoveTab); });
    });
    document.getElementById('ceAddRefTab')?.addEventListener('click', function () { openRefModal(null); });
  };

  var renderMediaTab = function () {
    var el = document.getElementById('ceInspectorMedia');
    if (!el) return;
    var html = '<div class="mb-2 d-flex gap-2 flex-wrap">' +
      '<button class="btn btn-primary btn-sm btn-sm" id="ceBrowseMediaBtn"><i class="bi bi-collection"></i> Browse library</button>' +
      '<label class="btn btn-sm" style="cursor:pointer"><i class="bi bi-upload"></i> Upload<input type="file" id="ceMediaUploadBtn" accept="image/jpeg,image/png,image/webp" style="display:none"></label>' +
      '</div>';
    html += '<div id="ceSelectedImage" class="mb-2"></div>';
    if (state.mediaItems.length) {
      html += '<div class="fw-semibold small mb-2" style="color:var(--ce-text)">Recent media</div>';
      state.mediaItems.slice(0, 5).forEach(function (media) {
        var isImage = media.mimeType && media.mimeType.indexOf('image/') === 0;
        html += '<div class="ce-media-thumb" data-media-select="' + media.uuid + '">' +
          (isImage
            ? '<img class="ce-media-thumb__img" src="' + API.storageView + '/' + (media.storageFileUuid || '') + '/view" alt="" loading="lazy">'
            : '<div class="ce-media-thumb__img d-flex align-items-center justify-content-center text-muted"><i class="bi bi-file-earmark-image"></i></div>') +
          '<div class="ce-media-thumb__info"><div class="ce-media-thumb__name">' + escHtml(media.originalName) + '</div><div class="ce-media-thumb__size">' + escHtml(media.mimeType || '') + '</div></div></div>';
      });
    } else {
      html += '<div class="text-muted small">No media available</div>';
    }
    el.innerHTML = html;
    document.getElementById('ceBrowseMediaBtn')?.addEventListener('click', openMediaModal);
    document.getElementById('ceMediaUploadBtn')?.addEventListener('change', function () {
      if (this.files?.[0]) uploadMedia(this.files[0]);
      this.value = '';
    });
    el.querySelectorAll('[data-media-select]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var mediaUuid = this.dataset.mediaSelect;
        var media = state.mediaItems.find(function (m) { return m.uuid === mediaUuid; });
        if (media) useMedia(media);
      });
    });
    renderImageInfo();
  };

  var renderImageInfo = function () {
    var el = document.getElementById('ceSelectedImage');
    if (!el) return;
    // Images are now rendered as custom embeds inside Quill
    // Selected image info is shown when clicking an embed in the editor
    el.innerHTML = '<div class="text-muted small">Select an image embed in the editor to configure</div>';
  };

  var renderHistoryTab = function () {
    var el = document.getElementById('ceInspectorHistory');
    if (!el) return;
    if (!state.history.length) {
      el.innerHTML = '<div class="ce-empty-state"><div class="ce-empty-state__icon"><i class="bi bi-clock-history"></i></div><div class="ce-empty-state__text">No revision history</div></div>';
      return;
    }
    el.innerHTML = state.history.map(function (entry) {
      return '<div class="ce-history-entry">' +
        '<div class="ce-history-entry__header">' +
        '<span class="ce-history-entry__version">v' + (entry.versionNumber || 0) + '</span>' +
        '<span class="ce-history-entry__status ce-history-entry__status--' + (entry.revisionStatus || 'draft') + '">' + (entry.revisionStatus || '') + '</span>' +
        '</div>' +
        (entry.publishedAt ? '<div class="ce-history-entry__meta">' + new Date(entry.publishedAt).toLocaleDateString() + '</div>' : '') +
        (entry.changeSummary ? '<div class="ce-history-entry__summary">' + escHtml(entry.changeSummary) + '</div>' : '') +
        '<div class="ce-history-entry__actions">' +
        '<button class="btn btn-sm" data-history-preview="' + entry.revisionUuid + '" type="button"><i class="bi bi-eye"></i> Preview</button>' +
        '<button class="btn btn-sm" data-history-restore="' + entry.revisionUuid + '" type="button"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>' +
        '</div></div>';
    }).join('');
    el.querySelectorAll('[data-history-preview]').forEach(function (btn) {
      btn.addEventListener('click', function () { window.open(API.webRevisions + '/' + this.dataset.historyPreview, '_blank'); });
    });
    el.querySelectorAll('[data-history-restore]').forEach(function (btn) {
      btn.addEventListener('click', function () { restoreRevision(this.dataset.historyRestore); });
    });
  };

  // ── Media modal ───────────────────────────────────────────────────────

  var renderMediaModal = function () {
    var grid = document.getElementById('ceMediaGrid');
    if (!grid) return;
    if (!state.mediaItems.length) {
      grid.innerHTML = '<div class="ce-empty-state" style="grid-column:1/-1"><div class="ce-empty-state__icon"><i class="bi bi-image"></i></div><div class="ce-empty-state__text">No media found</div></div>';
      return;
    }
    grid.innerHTML = state.mediaItems.map(function (media) {
      var sel = media.uuid === state.selectedMediaUuid;
      var isImage = media.mimeType && media.mimeType.indexOf('image/') === 0;
      return '<div class="ce-media-modal__card' + (sel ? ' ce-media-modal__card--selected' : '') + '" data-media-card="' + media.uuid + '">' +
        (isImage
          ? '<img class="ce-media-modal__card-img" src="' + API.storageView + '/' + (media.storageFileUuid || '') + '/view" alt="' + escHtml(media.originalName) + '" loading="lazy">'
          : '<div class="ce-media-modal__card-img d-flex align-items-center justify-content-center text-muted" style="font-size:2rem"><i class="bi bi-file-earmark-image"></i></div>') +
        '<div class="ce-media-modal__card-info"><div class="ce-media-modal__card-name">' + escHtml(media.originalName) + '</div>' +
        '<div class="ce-media-modal__card-size">' + formatSize(media.sizeBytes) + '</div></div></div>';
    }).join('');
    grid.querySelectorAll('[data-media-card]').forEach(function (card) {
      card.addEventListener('click', function () {
        var uuid = this.dataset.mediaCard;
        var media = state.mediaItems.find(function (m) { return m.uuid === uuid; });
        if (media) selectMedia(media);
      });
    });
  };

  var formatSize = function (bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  };

  // ── Overflow menu toggle ──────────────────────────────────────────────

  var toggleOverflow = function (e) {
    e.stopPropagation();
    state.overflowOpen = !state.overflowOpen;
    var menu = document.getElementById('ceOverflowMenu');
    if (!menu) return;
    menu.classList.toggle('ce-overflow-menu--open', state.overflowOpen);
    if (state.overflowOpen) {
      var backdrop = document.createElement('div');
      backdrop.className = 'ce-overflow-menu__backdrop';
      backdrop.id = 'ceOverflowBackdrop';
      backdrop.addEventListener('click', function () {
        state.overflowOpen = false;
        menu.classList.remove('ce-overflow-menu--open');
        this.remove();
      });
      document.body.appendChild(backdrop);
    } else {
      var b = document.getElementById('ceOverflowBackdrop');
      if (b) b.remove();
    }
  };

  // ── Panel layout management ───────────────────────────────────────────

  var layoutKey = 'ce_layout_' + (boot.pageUuid || 'default');

  var defaultLayout = {
    navWidth: 240,
    inspectorWidth: 320,
    navCollapsed: false,
    inspectorCollapsed: false,
    focusMode: false,
  };

  var panelLayout = {};

  var loadLayout = function () {
    try {
      var saved = localStorage.getItem(layoutKey);
      var parsed = saved ? JSON.parse(saved) : {};
      panelLayout = Object.assign({}, defaultLayout, parsed);
    } catch (e) {
      panelLayout = Object.assign({}, defaultLayout);
    }
  };

  var saveLayout = function () {
    try {
      localStorage.setItem(layoutKey, JSON.stringify(panelLayout));
    } catch (e) { /* quota exceeded or private mode */ }
  };

  var applyPanelLayout = function () {
    var nav = document.getElementById('ceSectionNav');
    var inspector = document.getElementById('ceInspector');
    var body = document.querySelector('.ce-body');
    var focusBtn = document.getElementById('ceFocusModeBtn');
    if (!nav || !inspector || !body) return;

    // Set widths
    nav.style.width = panelLayout.navWidth + 'px';
    inspector.style.width = panelLayout.inspectorWidth + 'px';

    // Collapsed states
    nav.classList.toggle('ce-panel--collapsed', panelLayout.navCollapsed);
    inspector.classList.toggle('ce-panel--collapsed', panelLayout.inspectorCollapsed);

    // Focus mode
    body.classList.toggle('ce-focus-mode', panelLayout.focusMode);
    if (focusBtn) {
      focusBtn.classList.toggle('ce-layout-btn--active', panelLayout.focusMode);
      focusBtn.title = panelLayout.focusMode ? 'Exit focus mode' : 'Focus mode — collapse side panels';
    }
  };

  // ── Drag-to-resize ────────────────────────────────────────────────────

  var setupResizeHandle = function (handleId, side, setter) {
    var handle = document.getElementById(handleId);
    if (!handle) return;

    var startX = 0;
    var startSize = 0;
    var isDragging = false;

    var onMouseDown = function (e) {
      e.preventDefault();
      isDragging = true;
      startX = e.clientX;
      startSize = side === 'nav' ? panelLayout.navWidth : panelLayout.inspectorWidth;
      document.body.classList.add('ce-dragging');
      handle.classList.add('ce-resize-handle--resizing');
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
    };

    var onMouseMove = function (e) {
      if (!isDragging) return;
      var delta = e.clientX - startX;
      var minSize = 180;
      var maxSize = 360;
      var defaultSize = 240;
      var minWorkspace = 560;

      if (side === 'inspector') {
        delta = -delta;
        minSize = 260;
        maxSize = 480;
        defaultSize = 320;
      }

      var nav = document.getElementById('ceSectionNav');
      var insp = document.getElementById('ceInspector');
      var bodyWidth = document.querySelector('.ce-body')?.offsetWidth || 1200;

      var newSize = Math.max(minSize, Math.min(maxSize, startSize + delta));

      // Ensure workspace doesn't shrink below minimum
      var otherSize = side === 'nav'
        ? (panelLayout.inspectorCollapsed ? 48 : panelLayout.inspectorWidth)
        : (panelLayout.navCollapsed ? 48 : panelLayout.navWidth);

      var handlesWidth = 8; // two handles
      var workspaceWidth = bodyWidth - newSize - otherSize - handlesWidth;
      if (workspaceWidth < minWorkspace) {
        newSize = bodyWidth - otherSize - handlesWidth - minWorkspace;
        newSize = Math.max(minSize, Math.min(maxSize, newSize));
      }

      setter(newSize);
      var el = side === 'nav' ? nav : insp;
      if (el) el.style.width = newSize + 'px';
    };

    var onMouseUp = function () {
      if (!isDragging) return;
      isDragging = false;
      document.body.classList.remove('ce-dragging');
      handle.classList.remove('ce-resize-handle--resizing');
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
      saveLayout();
    };

    handle.addEventListener('mousedown', onMouseDown);
  };

  // ── Collapse / expand panels ──────────────────────────────────────────

  var togglePanelCollapse = function (side) {
    if (side === 'nav') {
      panelLayout.navCollapsed = !panelLayout.navCollapsed;
      if (panelLayout.navCollapsed) panelLayout.focusMode = false;
    } else {
      panelLayout.inspectorCollapsed = !panelLayout.inspectorCollapsed;
      if (panelLayout.inspectorCollapsed) panelLayout.focusMode = false;
    }
    applyPanelLayout();
    saveLayout();
  };

  var expandPanel = function (side) {
    if (side === 'nav' && panelLayout.navCollapsed) {
      panelLayout.navCollapsed = false;
    } else if (side === 'inspector' && panelLayout.inspectorCollapsed) {
      panelLayout.inspectorCollapsed = false;
    }
    applyPanelLayout();
    saveLayout();
  };

  var toggleFocusMode = function () {
    panelLayout.focusMode = !panelLayout.focusMode;
    if (panelLayout.focusMode) {
      panelLayout.navCollapsed = true;
      panelLayout.inspectorCollapsed = true;
    }
    applyPanelLayout();
    saveLayout();
  };

  // ── Responsive toggles ────────────────────────────────────────────────

  var toggleNav = function () {
    var nav = document.getElementById('ceSectionNav');
    var overlay = document.getElementById('ceNavOverlay');
    // Restore width from layout if opening on tablet
    if (!nav?.classList.contains('ce-nav--open')) {
      nav.style.width = panelLayout.navCollapsed ? '' : (panelLayout.navWidth + 'px');
    }
    nav?.classList.toggle('ce-nav--open');
    overlay?.classList.toggle('ce-nav-overlay--visible');
  };

  var toggleInspectorPane = function () {
    var insp = document.getElementById('ceInspector');
    var overlay = document.getElementById('ceInspectorOverlay');
    if (!insp?.classList.contains('ce-inspector--open')) {
      insp.style.width = panelLayout.inspectorCollapsed ? '' : (panelLayout.inspectorWidth + 'px');
    }
    insp?.classList.toggle('ce-inspector--open');
    overlay?.classList.toggle('ce-inspector-overlay--visible');
  };

  // ── Init ──────────────────────────────────────────────────────────────

  var init = function () {
    // ──── Header buttons ────
    document.getElementById('ceSaveDraft')?.addEventListener('click', saveDraft);
    document.getElementById('cePublishBtn')?.addEventListener('click', publishDraft);
    document.getElementById('cePreviewBtn')?.addEventListener('click', function () { window.open(API.webPreview, '_blank'); });
    document.getElementById('ceOverflowToggle')?.addEventListener('click', toggleOverflow);
    document.getElementById('ceOverflowArchive')?.addEventListener('click', function () {
      state.overflowOpen = false;
      document.getElementById('ceOverflowMenu')?.classList.remove('ce-overflow-menu--open');
      var b = document.getElementById('ceOverflowBackdrop'); if (b) b.remove();
      if (confirm('Archive this page?')) archivePage();
    });
    document.getElementById('ceOverflowDuplicate')?.addEventListener('click', function () {
      state.overflowOpen = false;
      document.getElementById('ceOverflowMenu')?.classList.remove('ce-overflow-menu--open');
      var b = document.getElementById('ceOverflowBackdrop'); if (b) b.remove();
      setNotice('Duplicate coming soon.', 'info');
    });
    // Mobile
    document.getElementById('ceMobileSave')?.addEventListener('click', saveDraft);
    document.getElementById('ceMobilePublish')?.addEventListener('click', publishDraft);
    // Begin draft
    document.getElementById('ceBeginDraft')?.addEventListener('click', function () {
      window.location.reload();
    });

    // ──── Section nav ────
    document.getElementById('ceAddSection')?.addEventListener('click', addSection);

    // ──── Insert actions ────
    document.getElementById('ceInsertImage')?.addEventListener('click', function () {
      openMediaModal();
    });
    document.getElementById('ceInsertReference')?.addEventListener('click', function () {
      openRefModal(null);
    });
    document.getElementById('ceInsertCallout')?.addEventListener('click', function () {
      if (!quill) return;
      var range = quill.getSelection(true);
      quill.insertText(range.index, '\u{1F4CC} ', 'bold', true);
      quill.insertText(range.index + 2, 'Callout: ', { bold: true }, true);
      quill.setSelection(range.index + 10, 0);
      quill.format('blockquote', true);
      state.isDirty = true;
    });

    // ──── Inspector tabs ────
    $$('.ce-inspector__tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var t = this.dataset.inspectorTab;
        if (t) {
          state.inspectorTab = t;
          renderInspector();
          if (t === 'references') renderReferencesTab();
          if (t === 'media') renderMediaTab();
          if (t === 'history') renderHistoryTab();
        }
      });
    });

    // ──── Responsive toggles ────
    document.getElementById('ceNavToggle')?.addEventListener('click', toggleNav);
    document.getElementById('ceInspectorToggle')?.addEventListener('click', toggleInspectorPane);
    document.getElementById('ceNavOverlay')?.addEventListener('click', toggleNav);
    document.getElementById('ceInspectorOverlay')?.addEventListener('click', toggleInspectorPane);

    // ──── Reference modal ────
    document.getElementById('ceSaveReference')?.addEventListener('click', saveReference);
    document.getElementById('ceRefCancel')?.addEventListener('click', function () {
      bootstrap.Modal.getInstance(document.getElementById('ceReferenceModal'))?.hide();
    });

    // ──── Media modal ────
    document.getElementById('ceMediaUploadDropzone')?.addEventListener('click', function () {
      document.getElementById('ceMediaUploadInput')?.click();
    });
    document.getElementById('ceMediaUploadInput')?.addEventListener('change', function () {
      if (this.files?.[0]) uploadMedia(this.files[0]);
      this.value = '';
    });
    document.getElementById('ceMediaSearch')?.addEventListener('input', function () {
      var q = this.value.toLowerCase();
      var cards = $$('[data-media-card]');
      cards.forEach(function (card) {
        var name = (card.querySelector('.ce-media-modal__card-name')?.textContent || '').toLowerCase();
        card.style.display = name.indexOf(q) > -1 ? '' : 'none';
      });
    });

    // ──── Heading input ────
    document.getElementById('ceSectionHeading')?.addEventListener('input', function () {
      var sec = currentSection();
      if (sec) { sec.heading = this.value; state.isDirty = true; renderSectionNav(); }
    });

    // ──── Panel layout ────
    loadLayout();
    applyPanelLayout();

    // Resize handles
    setupResizeHandle('ceResizeHandleLeft', 'nav', function (v) { panelLayout.navWidth = v; });
    setupResizeHandle('ceResizeHandleRight', 'inspector', function (v) { panelLayout.inspectorWidth = v; });

    // Collapse buttons
    document.getElementById('ceCollapseNav')?.addEventListener('click', function () { togglePanelCollapse('nav'); });
    document.getElementById('ceCollapseInspector')?.addEventListener('click', function () { togglePanelCollapse('inspector'); });

    // Rail expand buttons
    document.querySelectorAll('[data-ce-expand="nav"]').forEach(function (btn) {
      btn.addEventListener('click', function () { expandPanel('nav'); });
    });
    document.querySelectorAll('[data-ce-expand="inspector"]').forEach(function (btn) {
      btn.addEventListener('click', function () { expandPanel('inspector'); });
    });

    // Focus mode
    document.getElementById('ceFocusModeBtn')?.addEventListener('click', toggleFocusMode);

    // ──── Keyboard shortcut ────
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveDraft(); }
    });

    // ──── Embed click handler (open inspector when clicking image/reference in editor) ────
    document.getElementById('ceQuillEditor')?.addEventListener('click', function (e) {
      var imageBlock = e.target.closest('.content-image-block');
      var refBlock = e.target.closest('.content-reference-block');
      if (imageBlock) {
        state.selectedMediaUuid = imageBlock.dataset.mediaUuid || '';
        state.inspectorTab = 'media';
        renderInspector();
        renderMediaTab();
        return;
      }
      if (refBlock) {
        state.inspectorTab = 'references';
        renderInspector();
        renderReferencesTab();
        return;
      }
    });

    // ──── Init Quill ────
    if (hasDraft) {
      initQuill();
    }

    // ──── Initial render ────
    renderAll();
    loadEditorState();
    if (hasDraft) {
      loadMedia();
    }
  };

  // Start
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
