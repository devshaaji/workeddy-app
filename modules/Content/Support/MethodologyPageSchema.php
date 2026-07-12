<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Support;

use WorkEddy\Modules\Content\Domain\Contracts\ContentPageSchema;

final class MethodologyPageSchema implements ContentPageSchema
{
    public function pageKey(): string
    {
        return MethodologyPageDefinition::PAGE_KEY;
    }

    public function validate(string $targetStatus, array $snapshot): ContentValidationResult
    {
        $errors = [];
        $sections = $snapshot['sections'] ?? null;
        if (!is_array($sections) || $sections === []) {
            $errors['sections'] = 'At least one section is required.';
            return new ContentValidationResult($errors);
        }

        $requiredKeys = MethodologyPageDefinition::sectionKeys();
        $seen = [];

        foreach ($sections as $index => $section) {
            $sectionKey = is_array($section) ? (string) ($section['sectionKey'] ?? '') : '';
            if ($sectionKey === '') {
                $errors['sections.' . $index] = 'Each section requires a sectionKey.';
                continue;
            }
            if (isset($seen[$sectionKey])) {
                $errors['sections.' . $index] = 'Duplicate sectionKey "' . $sectionKey . '" is not allowed.';
            }
            $seen[$sectionKey] = true;

            if (!in_array($sectionKey, $requiredKeys, true)) {
                $errors['sections.' . $index] = 'Unexpected sectionKey "' . $sectionKey . '".';
            }

            $blocks = is_array($section) ? ($section['blocks'] ?? null) : null;
            if (!is_array($blocks) || $blocks === []) {
                $errors['sections.' . $index . '.blocks'] = 'Each section requires at least one block.';
                continue;
            }

            foreach ($blocks as $blockIndex => $block) {
                $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
                if (!in_array($type, ['paragraph', 'rich_text', 'image', 'list'], true)) {
                    $errors['sections.' . $index . '.blocks.' . $blockIndex] = 'Block type "' . $type . '" is not allowed.';
                    continue;
                }
                if ($type === 'image') {
                    $mediaUuid = is_array($block) ? trim((string) ($block['mediaUuid'] ?? '')) : '';
                    $altText = is_array($block) ? (string) ($block['altText'] ?? '') : '';
                    $display = is_array($block) ? (string) ($block['display'] ?? '') : '';
                    if ($mediaUuid === '') {
                        $errors['sections.' . $index . '.blocks.' . $blockIndex . '.mediaUuid'] = 'Image blocks require mediaUuid.';
                    }
                    if ($display !== '' && !in_array($display, ['inline', 'wide', 'full-width', 'left', 'right'], true)) {
                        $errors['sections.' . $index . '.blocks.' . $blockIndex . '.display'] = 'Invalid display mode.';
                    }
                    if (($block['decorative'] ?? false) !== true && trim($altText) === '') {
                        $errors['sections.' . $index . '.blocks.' . $blockIndex . '.altText'] = 'Meaningful images require alt text.';
                    }
                }
            }
        }

        foreach ($requiredKeys as $key) {
            if (!isset($seen[$key])) {
                $errors['missing.' . $key] = 'Required section "' . $key . '" is missing.';
            }
        }

        if ($targetStatus === 'published') {
            $actualOrder = array_values(array_map(static fn(array $section): string => (string) ($section['sectionKey'] ?? ''), $sections));
            if ($actualOrder !== $requiredKeys) {
                $errors['sections.order'] = 'Methodology sections must follow the canonical order.';
            }
        }

        return new ContentValidationResult($errors);
    }
}
