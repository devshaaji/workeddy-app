<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Support;

final class ContentSnapshotNormalizer
{
    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function normalize(array $snapshot): array
    {
        $sections = is_array($snapshot['sections'] ?? null) ? array_values($snapshot['sections']) : [];
        $references = is_array($snapshot['references'] ?? null) ? array_values($snapshot['references']) : [];

        foreach ($sections as $index => $section) {
            if (!is_array($section)) {
                continue;
            }

            $normalized = $section;
            $blocks = $normalized['blocks'] ?? null;
            if (!is_array($blocks) && is_array($normalized['content'] ?? null)) {
                $normalized['blocks'] = self::blocksFromContent((array) $normalized['content']);
            } elseif (is_array($blocks)) {
                $normalized['blocks'] = array_values($blocks);
            } else {
                $normalized['blocks'] = [];
            }

            $sections[$index] = $normalized;
        }

        $snapshot['sections'] = $sections;
        $snapshot['references'] = $references;

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $content
     * @return list<array<string, mixed>>
     */
    private static function blocksFromContent(array $content): array
    {
        $format = (string) ($content['format'] ?? '');
        if ($format !== 'quill_delta') {
            return [];
        }

        $delta = $content['delta'] ?? null;
        $ops = is_array($delta['ops'] ?? null) ? $delta['ops'] : [];
        $blocks = [];
        $lineBuffer = '';
        $listItems = [];

        $flushLine = static function (array $attributes = []) use (&$lineBuffer, &$blocks, &$listItems): void {
            $line = trim($lineBuffer);
            $lineBuffer = '';

            if (($attributes['list'] ?? null) !== null) {
                if ($line !== '') {
                    $listItems[] = $line;
                }
                return;
            }

            if ($listItems !== []) {
                $blocks[] = [
                    'type' => 'list',
                    'items' => array_values($listItems),
                ];
                $listItems = [];
            }

            if ($line === '') {
                return;
            }

            if (($attributes['header'] ?? null) !== null || !empty($attributes['blockquote']) || !empty($attributes['bold']) || !empty($attributes['italic']) || !empty($attributes['link'])) {
                $blocks[] = [
                    'type' => 'rich_text',
                    'body' => ContentRichTextRenderer::render([
                        'format' => 'quill_delta',
                        'delta' => ['ops' => [[
                            'insert' => html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n",
                            'attributes' => $attributes,
                        ]]],
                    ]),
                ];
                return;
            }

            $blocks[] = [
                'type' => 'paragraph',
                'text' => html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
        };

        $flushList = static function () use (&$listItems, &$blocks): void {
            $items = array_values(array_filter(array_map(
                static fn(mixed $item): string => trim((string) $item),
                $listItems,
            ), static fn(string $item): bool => $item !== ''));

            if ($items !== []) {
                $blocks[] = [
                    'type' => 'list',
                    'items' => $items,
                ];
            }

            $listItems = [];
        };

        foreach ($ops as $op) {
            if (!is_array($op) || !array_key_exists('insert', $op)) {
                continue;
            }

            $insert = $op['insert'];
            $attributes = is_array($op['attributes'] ?? null) ? $op['attributes'] : [];

            if (is_string($insert)) {
                $parts = explode("\n", str_replace("\r", '', $insert));
                $lastIndex = count($parts) - 1;
                foreach ($parts as $index => $part) {
                    if ($part !== '') {
                        $lineBuffer .= self::renderInlineBlockText($part, $attributes);
                    }

                    if ($index < $lastIndex) {
                        $flushLine($attributes);
                    }
                }
                continue;
            }

            if (!is_array($insert)) {
                continue;
            }

            $flushLine();
            $flushList();

            if (is_array($insert['contentImage'] ?? null)) {
                $image = $insert['contentImage'];
                $blocks[] = [
                    'type' => 'image',
                    'mediaUuid' => trim((string) ($image['mediaUuid'] ?? '')),
                    'storageFileUuid' => trim((string) ($image['storageFileUuid'] ?? '')),
                    'previewUrl' => trim((string) ($image['previewUrl'] ?? '')),
                    'altText' => (string) ($image['altText'] ?? ''),
                    'caption' => (string) ($image['caption'] ?? ''),
                    'display' => (string) ($image['display'] ?? 'wide'),
                ];
            }
        }

        $flushLine();
        $flushList();

        return $blocks;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function renderInlineBlockText(string $text, array $attributes): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        if (!empty($attributes['link']) && is_string($attributes['link'])) {
            $href = htmlspecialchars($attributes['link'], ENT_QUOTES, 'UTF-8');
            $html = '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $html . '</a>';
        }
        if (!empty($attributes['italic'])) {
            $html = '<em>' . $html . '</em>';
        }
        if (!empty($attributes['bold'])) {
            $html = '<strong>' . $html . '</strong>';
        }

        return $html;
    }
}
