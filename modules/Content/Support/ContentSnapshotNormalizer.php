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

            unset($normalized['content']);
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
        $paragraphBuffer = '';
        $listItems = [];

        $flushParagraphs = static function () use (&$paragraphBuffer, &$blocks): void {
            if ($paragraphBuffer === '') {
                return;
            }

            $segments = preg_split("/\n+/", str_replace("\r", '', $paragraphBuffer)) ?: [];
            foreach ($segments as $segment) {
                $text = trim((string) $segment);
                if ($text === '') {
                    continue;
                }

                $blocks[] = [
                    'type' => 'paragraph',
                    'text' => $text,
                ];
            }

            $paragraphBuffer = '';
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
                if (($attributes['list'] ?? null) !== null) {
                    $flushParagraphs();
                    $segments = preg_split("/\n+/", str_replace("\r", '', $insert)) ?: [];
                    foreach ($segments as $segment) {
                        $text = trim((string) $segment);
                        if ($text !== '') {
                            $listItems[] = $text;
                        }
                    }
                    continue;
                }

                $flushList();
                $paragraphBuffer .= $insert;
                continue;
            }

            if (!is_array($insert)) {
                continue;
            }

            $flushParagraphs();
            $flushList();

            if (is_array($insert['contentImage'] ?? null)) {
                $image = $insert['contentImage'];
                $blocks[] = [
                    'type' => 'image',
                    'mediaUuid' => trim((string) ($image['mediaUuid'] ?? '')),
                    'altText' => (string) ($image['altText'] ?? ''),
                    'caption' => (string) ($image['caption'] ?? ''),
                    'display' => (string) ($image['display'] ?? 'wide'),
                ];
            }
        }

        $flushParagraphs();
        $flushList();

        return $blocks;
    }
}
