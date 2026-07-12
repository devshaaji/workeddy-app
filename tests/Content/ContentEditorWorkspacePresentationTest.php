<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use PHPUnit\Framework\TestCase;

final class ContentEditorWorkspacePresentationTest extends TestCase
{
    public function test_editor_view_exposes_section_reference_and_media_workspace_controls(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/editor.php');

        self::assertIsString($view);
        self::assertStringContainsString('ceSectionList', $view);
        self::assertStringContainsString('ceSectionHeading', $view);
        self::assertStringContainsString('ceReferenceList', $view);
        self::assertStringContainsString('ceInsertReference', $view);
        self::assertStringContainsString('ceMediaGrid', $view);
        self::assertStringContainsString('ceMediaUploadInput', $view);
        self::assertStringContainsString('ceSelectedImage', $view);
    }

    public function test_editor_script_handles_section_switching_reference_updates_media_loading_and_refresh(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/public/assets/js/content-editor.js');
        $controller = file_get_contents($root . '/modules/Content/Presentation/ContentPageController.php');
        $view = file_get_contents($root . '/modules/Content/Presentation/Views/editor.php');

        self::assertIsString($script);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString('activeSectionKey', $script);
        self::assertStringContainsString('renderSectionNav', $script);
        self::assertStringContainsString('renderReferencesTab', $script);
        self::assertStringContainsString('loadMedia', $script);
        self::assertStringContainsString('/api/v1/content/media', $script);
        self::assertStringContainsString('loadEditorState', $script);
        self::assertStringContainsString('pageApiBaseUrl', $script);
        self::assertStringContainsString('pageWebBaseUrl', $script);
        self::assertStringContainsString('/preview', $script);
        self::assertStringContainsString('/revisions', $script);
        self::assertStringContainsString('/api/v1/content/pages', $script);
        self::assertStringContainsString('webPreview: pageWebBaseUrl + \'/preview\'', $script);
        self::assertStringContainsString('webRevisions: pageWebBaseUrl + \'/revisions\'', $script);
        self::assertStringContainsString("window.open(API.webPreview, '_blank');", $script);
        self::assertStringContainsString("window.open(API.webRevisions + '/' + this.dataset.historyPreview, '_blank');", $script);
        self::assertStringContainsString('ceMediaAltText', $script);
        self::assertStringContainsString('ceMediaCaption', $script);
        self::assertStringContainsString('ceMediaDisplay', $script);
        self::assertStringContainsString('renderImageInfo', $script);
        self::assertStringContainsString('class ContentImageBlot extends BlockEmbed', $script);
        self::assertStringContainsString('class ContentReferenceBlot extends BlockEmbed', $script);
        self::assertStringContainsString('insertReferenceMarker', $script);
        self::assertStringNotContainsString('insertContentReference(data);', $script);
        self::assertStringNotContainsString("find(function (r) { return r._key === this.dataset.refEditTab; })", $script);
        self::assertStringNotContainsString("find(function (m) { return m.uuid === this.dataset.mediaSelect; })", $script);
        self::assertStringNotContainsString('pageList', $script);
        self::assertStringNotContainsString("'pageList' =>", $controller);
        self::assertStringNotContainsString('@var list<array<string, mixed>> $pageList', $view);
        self::assertStringContainsString("'history' => \$this->pages->listRevisionHistoryByPageUuid(\$pageUuid)", $controller);
        self::assertStringContainsString('beginDraftFromPublished', $controller);
        self::assertStringContainsString('$jsonScript = static fn(mixed $value): string => (string) json_encode(', $view);
        self::assertStringContainsString('JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT', $view);
        self::assertStringNotContainsString("htmlspecialchars((string) json_encode(\$boot", $view);
        self::assertStringNotContainsString("htmlspecialchars((string) json_encode(\$history", $view);
        self::assertStringNotContainsString("htmlspecialchars((string) json_encode(\$draft->snapshot", $view);
    }
}
