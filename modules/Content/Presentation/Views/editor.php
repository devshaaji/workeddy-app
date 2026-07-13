<?php

/** @var array<string, mixed> $page */
/** @var ?\WorkEddy\Modules\Content\Application\DTOs\ContentPreviewPage $draft */
/** @var list<array<string, mixed>> $history */
$pageTitle = 'Content Editor';
$pageCss = ['vendor/quill/quill.snow.css', 'css/content-editor.css'];
$pageScripts = ['vendor/quill/quill.min.js', 'js/content-editor.js'];

$pageUuid = (string) ($page['pageUuid'] ?? '');
$pageKey = (string) ($page['pageKey'] ?? '');
$pageAudience = (string) ($page['audience'] ?? 'internal');
$pageTemplate = (string) ($page['templateKey'] ?? 'internal_default');
$pageStatus = (string) ($page['status'] ?? 'draft');
$lockVersion = (int) ($page['lockVersion'] ?? 0);
$seoTitle = (string) ($page['seoTitle'] ?? '');
$seoDescription = (string) ($page['seoDescription'] ?? '');
$draftTitle = $draft !== null ? $draft->title : ((string) ($page['title'] ?? ''));

$boot = [
    'pageUuid' => $pageUuid,
    'pageKey' => $pageKey,
    'pageTitle' => $draftTitle ?: 'Content Page',
    'audience' => $pageAudience,
    'templateKey' => $pageTemplate,
    'pageStatus' => $pageStatus,
    'lockVersion' => $lockVersion,
    'seoTitle' => $seoTitle,
    'seoDescription' => $seoDescription,
    'pageApiBaseUrl' => '/api/v1/content/pages/' . rawurlencode($pageUuid),
    'pageWebBaseUrl' => '/content/pages/' . rawurlencode($pageUuid),
];

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$jsonScript = static fn(mixed $value): string => (string) json_encode(
    $value,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
);
?>

<section class="content-editor" id="contentEditorApp">

    <!-- ═══════════════════════════ STICKY HEADER ════════════════════════ -->

    <header class="ce-header" id="ceHeader">
        <a href="/content" class="ce-header-back" id="ceBackLink">
            <i class="bi bi-arrow-left"></i>
            <span>Content</span>
        </a>
        <div class="ce-header-divider"></div>
        <input class="ce-header-title" id="ceHeaderTitle" type="text"
            value="<?= $e($draftTitle) ?>"
            placeholder="Page title"
            autocomplete="off">
        <span class="ce-status-badge ce-status-badge--<?= $e($pageStatus) ?>" id="ceStatusBadge"><?= $e($pageStatus) ?></span>
        <span class="ce-save-state" id="ceSaveState">
            <span class="ce-save-state__dot"></span>
            <span class="ce-save-state__label"></span>
        </span>
        <div class="ce-header-spacer"></div>

        <!-- Panel layout controls (desktop) -->
        <button class="btn ce-layout-btn" id="ceFocusModeBtn" type="button" title="Focus mode — collapse side panels">
            <i class="bi bi-arrows-angle-expand"></i>
        </button>

        <!-- Responsive toggles (visible on tablet) -->
        <button class="btn ce-nav__toggle" id="ceNavToggle" type="button" title="Toggle section list">
            <i class="bi bi-list-ol"></i>
        </button>
        <button class="btn ce-inspector__toggle" id="ceInspectorToggle" type="button" title="Toggle inspector">
            <i class="bi bi-info-circle"></i>
        </button>

        <button class="btn" id="cePreviewBtn" type="button" data-ce-action="1">
            <i class="bi bi-eye"></i>
            <span class="ce-btn__label">Preview</span>
        </button>
        <button class="btn btn-primary" id="ceSaveDraft" type="button" data-ce-action="1">
            <i class="bi bi-cloud-upload"></i>
            <span class="ce-btn__label">Save draft</span>
        </button>

        <div class="dropdown">
            <button class="btn btn-success" id="cePublishBtn" type="button" data-ce-action="1">
                <i class="bi bi-send"></i>
                <span class="ce-btn__label">Publish</span>
            </button>
            <button class="btn" id="ceOverflowToggle" type="button" title="More actions">
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow" id="ceOverflowMenu">
                <button class="dropdown-item" id="ceOverflowDuplicate" type="button">
                    <i class="bi bi-files"></i> Duplicate page
                </button>
                <div class="dropdown-divider"></div>
                <button class="dropdown-item text-danger" id="ceOverflowArchive" type="button">
                    <i class="bi bi-archive"></i> Archive page
                </button>
            </div>
        </div>
    </header>

    <!-- ═══════════════════════════ NOTICE BAR ════════════════════════════ -->

    <div class="ce-notice" id="ceNotice" style="display:none"></div>

    <!-- ═══════════════════════════ THREE-COLUMN BODY ════════════════════ -->

    <div class="ce-body">

        <!-- Section Navigator (Left) -->
        <nav class="ce-nav" id="ceSectionNav" data-ce-panel="nav">
            <div class="ce-nav__header">
                <span class="ce-nav__header-content">
                    <i class="bi bi-layers"></i>
                    <span class="ce-nav__header-label">Sections</span>
                    <span class="ce-nav__header-count text-muted ms-1 small fw-normal">(<?= count($draft?->snapshot['sections'] ?? []) ?>)</span>
                </span>
                <button class="ce-collapse-btn" id="ceCollapseNav" type="button" title="Collapse section navigator">
                    <i class="bi bi-chevron-double-left"></i>
                </button>
            </div>
            <!-- Icon rail (visible when collapsed) -->
            <div class="ce-nav__rail" id="ceNavRail">
                <button class="ce-rail-btn" title="Sections" data-ce-expand="nav"><i class="bi bi-layers"></i></button>
            </div>
            <div class="ce-nav__list" id="ceSectionList">
                <!-- Populated by JS -->
            </div>
            <div class="ce-nav__add">
                <button class="ce-nav__add-btn" id="ceAddSection" type="button" data-ce-action="1">
                    <i class="bi bi-plus-lg"></i> Add section
                </button>
            </div>
        </nav>

        <!-- Nav overlay (tablet/mobile) -->
        <div class="ce-nav-overlay" id="ceNavOverlay"></div>

        <!-- Resize handle: nav / workspace -->
        <div class="ce-resize-handle ce-resize-handle--left" id="ceResizeHandleLeft" title="Drag to resize"><span></span></div>

        <!-- Main Editor Workspace (Center) -->
        <main class="ce-workspace" id="ceWorkspace">
            <!-- Loading skeleton (visible until JS loads data) -->
            <?php if ($draft !== null): ?>
                <div class="ce-workspace__section ce-skeleton-container" id="ceSkeleton">
                    <div class="ce-skeleton__header">
                        <div class="ce-skeleton__line ce-skeleton__line--short"></div>
                    </div>
                    <div class="ce-skeleton__toolbar">
                        <div class="ce-skeleton__line ce-skeleton__line--micro"></div>
                        <div class="ce-skeleton__line ce-skeleton__line--micro"></div>
                        <div class="ce-skeleton__line ce-skeleton__line--micro"></div>
                        <div class="ce-skeleton__line ce-skeleton__line--micro"></div>
                    </div>
                    <div class="ce-skeleton__body">
                        <div class="ce-skeleton__line ce-skeleton__line--medium"></div>
                        <div class="ce-skeleton__line"></div>
                        <div class="ce-skeleton__line"></div>
                        <div class="ce-skeleton__line ce-skeleton__line--short"></div>
                        <div class="ce-skeleton__line ce-skeleton__line--block"></div>
                        <div class="ce-skeleton__line"></div>
                        <div class="ce-skeleton__line ce-skeleton__line--medium"></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($draft === null): ?>
                <div class="ce-workspace__empty" id="ceEmptyDraft">
                    <div class="ce-workspace__empty-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div>No active draft exists yet</div>
                    <button class="btn btn-primary" id="ceBeginDraft" type="button">Begin draft</button>
                </div>
            <?php else: ?>
                <div id="ceWorkspaceContent" class="ce-workspace__section" style="display:none">
                    <div class="ce-workspace__top">
                        <div class="ce-workspace__section-label" id="ceSectionNumber">Section</div>
                        <input class="ce-workspace__section-heading" id="ceSectionHeading" type="text" value="" placeholder="Section heading">
                        <div class="ce-workspace__section-description" id="ceSectionDescription"></div>
                    </div>
                    <div class="ce-editor-canvas">
                        <!-- Quill toolbar -->
                        <div id="ceQuillToolbar">
                            <span class="ql-formats">
                                <select class="ql-header">
                                    <option value="2">Heading 2</option>
                                    <option value="3">Heading 3</option>
                                    <option value="false">Normal</option>
                                </select>
                            </span>
                            <span class="ql-separator"></span>
                            <span class="ql-formats">
                                <button class="ql-bold" title="Bold"></button>
                                <button class="ql-italic" title="Italic"></button>
                            </span>
                            <span class="ql-separator"></span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered" title="Ordered list"></button>
                                <button class="ql-list" value="bullet" title="Bullet list"></button>
                            </span>
                            <span class="ql-separator"></span>
                            <span class="ql-formats">
                                <button class="ql-blockquote" title="Blockquote"></button>
                                <button class="ql-link" title="Link"></button>
                            </span>
                            <span class="ql-separator"></span>
                            <span class="ql-formats">
                                <button class="ql-undo" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <button class="ql-redo" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                            </span>
                            <span class="ql-separator"></span>
                            <span class="ql-formats">
                                <button class="ql-clean" title="Clear formatting"></button>
                            </span>
                        </div>
                        <!-- Quill editor container -->
                        <div id="ceQuillEditor" data-revision-uuid="<?= $draft !== null ? $e($draft->revisionUuid) : '' ?>"></div>
                        <!-- Insert actions -->
                        <div class="ce-insert-actions">
                            <button class="ce-insert-btn ce-insert-btn--image" id="ceInsertImage" type="button"><i class="bi bi-image"></i> Image</button>
                            <button class="ce-insert-btn ce-insert-btn--reference" id="ceInsertReference" type="button"><i class="bi bi-bookmark"></i> Reference</button>
                            <button class="ce-insert-btn ce-insert-btn--callout" id="ceInsertCallout" type="button"><i class="bi bi-chat-quote"></i> Callout</button>
                        </div>
                    </div>
                </div>
                <!-- Reference list below editor -->
                <div id="ceReferenceList" class="px-3 py-2 border-top">
                    <!-- Populated by JS -->
                </div>
            <?php endif; ?>
        </main>

        <!-- Resize handle: workspace / inspector -->
        <div class="ce-resize-handle ce-resize-handle--right" id="ceResizeHandleRight" title="Drag to resize"><span></span></div>

        <!-- Right Inspector -->
        <aside class="ce-inspector" id="ceInspector" data-ce-panel="inspector">
            <div class="ce-inspector__header">
                <span class="ce-inspector__header-label">Inspector</span>
                <button class="ce-collapse-btn" id="ceCollapseInspector" type="button" title="Collapse inspector">
                    <i class="bi bi-chevron-double-right"></i>
                </button>
            </div>
            <!-- Icon rail (visible when collapsed) -->
            <div class="ce-inspector__rail" id="ceInspectorRail">
                <button class="ce-rail-btn" title="Inspector" data-ce-expand="inspector"><i class="bi bi-info-circle"></i></button>
            </div>
            <div class="ce-inspector__tabs">
                <button class="ce-inspector__tab ce-inspector__tab--active" data-inspector-tab="page" type="button">Page</button>
                <button class="ce-inspector__tab" data-inspector-tab="section" type="button">Section</button>
                <button class="ce-inspector__tab" data-inspector-tab="references" type="button">Refs</button>
                <button class="ce-inspector__tab" data-inspector-tab="media" type="button">Media</button>
                <button class="ce-inspector__tab" data-inspector-tab="history" type="button">History</button>
            </div>
            <div class="ce-inspector__content">
                <div class="ce-inspector__panel ce-inspector__panel--active" id="ceInspectorPage">
                    <!-- Populated by JS -->
                </div>
                <div class="ce-inspector__panel" id="ceInspectorSection">
                    <!-- Populated by JS -->
                </div>
                <div class="ce-inspector__panel" id="ceInspectorReferences">
                    <!-- Populated by JS -->
                </div>
                <div class="ce-inspector__panel" id="ceInspectorMedia">
                    <div id="ceSelectedImage"></div>
                </div>
                <div class="ce-inspector__panel" id="ceInspectorHistory">
                    <!-- Populated by JS -->
                </div>
            </div>
        </aside>

        <!-- Inspector overlay (tablet/mobile) -->
        <div class="ce-inspector-overlay" id="ceInspectorOverlay"></div>

    </div>

    <!-- ═══════════════════════════ MOBILE ACTION BAR ════════════════════ -->

    <div class="ce-mobile-actions" id="ceMobileActions">
        <button class="btn" id="ceMobileSave" type="button" data-ce-action="1">
            <i class="bi bi-cloud-upload"></i> Save
        </button>
        <button class="btn btn-success" id="ceMobilePublish" type="button" data-ce-action="1">
            <i class="bi bi-send"></i> Publish
        </button>
    </div>

    <!-- ═══════════════════════════ MEDIA LIBRARY MODAL ══════════════════ -->

    <div class="modal fade ce-media-modal" id="ceMediaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Media library</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="ce-media-modal__toolbar">
                    <input class="ce-media-modal__search" id="ceMediaSearch" type="search" placeholder="Search media\u2026" autocomplete="off">
                    <div class="ce-media-modal__filters">
                        <span class="ce-media-modal__filter ce-media-modal__filter--active" data-media-filter="all">All</span>
                        <span class="ce-media-modal__filter" data-media-filter="image">Images</span>
                    </div>
                </div>
                <div class="ce-media-modal__body">
                    <div class="ce-media-modal__upload-area" id="ceMediaUploadDropzone">
                        <i class="bi bi-cloud-arrow-up" style="font-size:1.5rem;display:block;margin-bottom:0.25rem"></i>
                        <span class="small">Drop files or click to upload</span>
                        <input type="file" id="ceMediaUploadInput" accept="image/jpeg,image/png,image/webp" style="display:none">
                    </div>
                    <div class="ce-media-modal__grid" id="ceMediaGrid">
                        <!-- Populated by JS -->
                    </div>
                    <div class="ce-media-modal__detail" id="ceMediaDetail">
                        <div class="text-muted small text-center">Select an image to configure</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ceMediaUseImage" disabled>Use image</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════ REFERENCE EDITOR MODAL ════════════════ -->

    <div class="modal fade ce-ref-modal" id="ceReferenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ceReferenceModalTitle">Add reference</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section</label>
                        <div class="small text-muted" id="ceRefSectionKeyLabel">-</div>
                        <input type="hidden" id="ceRefSectionKey" value="">
                    </div>
                    <div class="form-group">
                        <label for="ceRefTitle">Title</label>
                        <input type="text" id="ceRefTitle" placeholder="Source title">
                    </div>
                    <div class="form-group">
                        <label for="ceRefAuthor">Author</label>
                        <input type="text" id="ceRefAuthor" placeholder="Author name">
                    </div>
                    <div class="form-group">
                        <label for="ceRefYear">Year</label>
                        <input type="text" id="ceRefYear" placeholder="e.g. 2025">
                    </div>
                    <div class="form-group">
                        <label for="ceRefUrl">URL</label>
                        <input type="url" id="ceRefUrl" placeholder="https://">
                    </div>
                    <div class="form-group">
                        <label for="ceRefCitation">Citation</label>
                        <textarea id="ceRefCitation" rows="3" placeholder="Full citation text"></textarea>
                    </div>
                    <input type="hidden" id="ceReferenceKey" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" id="ceRefCancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ceSaveReference">Save reference</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════ BOOT DATA ═════════════════════════════ -->

    <?php if ($draft !== null): ?>
        <script type="application/json" id="contentDraftSnapshot">
            <?= $jsonScript($draft->snapshot) ?>
        </script>
    <?php else: ?>
        <script type="application/json" id="contentDraftSnapshot">
            {
                "sections": [],
                "references": []
            }
        </script>
    <?php endif; ?>
    <script type="application/json" id="contentRevisionHistory">
        <?= $jsonScript($history) ?>
    </script>
    <script type="application/json" id="contentEditorBoot">
        <?= $jsonScript($boot) ?>
    </script>
</section>