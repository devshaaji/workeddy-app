<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Support;

final class ContentRichTextRenderer
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, int> $referenceIndexByKey
     * @param array<string, int> $referenceIndexByTitle
     */
    public static function render(array $content, array $referenceIndexByKey = [], array $referenceIndexByTitle = []): string
    {
        if ((string) ($content['format'] ?? '') !== 'quill_delta') {
            return '';
        }

        $delta = $content['delta'] ?? null;
        $ops = is_array($delta['ops'] ?? null) ? $delta['ops'] : [];
        if ($ops === []) {
            return '';
        }

        $html = [];
        $lineBuffer = '';
        $listType = null;
        $listItems = [];

        $flushList = static function () use (&$html, &$listType, &$listItems): void {
            if ($listType === null || $listItems === []) {
                $listType = null;
                $listItems = [];
                return;
            }

            $tag = $listType === 'ordered' ? 'ol' : 'ul';
            $html[] = '<' . $tag . '><li>' . implode('</li><li>', $listItems) . '</li></' . $tag . '>';
            $listType = null;
            $listItems = [];
        };

        $flushLine = static function (array $attributes = []) use (&$html, &$lineBuffer, &$listType, &$listItems, $flushList): void {
            $content = trim($lineBuffer);
            $lineBuffer = '';

            if (($attributes['list'] ?? null) !== null) {
                $item = $content !== '' ? $content : '<br>';
                $nextListType = $attributes['list'] === 'ordered' ? 'ordered' : 'bullet';
                if ($listType !== null && $listType !== $nextListType) {
                    $flushList();
                }

                $listType = $nextListType;
                $listItems[] = $item;
                return;
            }

            $flushList();
            if ($content === '') {
                return;
            }

            if (($attributes['header'] ?? null) === 2 || ($attributes['header'] ?? null) === '2') {
                $html[] = '<h2>' . $content . '</h2>';
                return;
            }

            if (($attributes['header'] ?? null) === 3 || ($attributes['header'] ?? null) === '3') {
                $html[] = '<h3>' . $content . '</h3>';
                return;
            }

            if (!empty($attributes['blockquote'])) {
                $html[] = '<blockquote>' . $content . '</blockquote>';
                return;
            }

            $html[] = '<p>' . $content . '</p>';
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
                        $lineBuffer .= self::renderInline($part, $attributes, $referenceIndexByTitle);
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

            if (is_array($insert['contentImage'] ?? null)) {
                $flushLine();
                $image = $insert['contentImage'];
                $storageFileUuid = trim((string) ($image['storageFileUuid'] ?? ''));
                $previewUrl = trim((string) ($image['previewUrl'] ?? ''));
                $caption = trim((string) ($image['caption'] ?? ''));
                $altText = trim((string) ($image['altText'] ?? ''));
                $src = $previewUrl;
                if ($src === '' && $storageFileUuid !== '') {
                    $src = '/api/v1/storage/files/' . rawurlencode($storageFileUuid) . '/view';
                }

                $figure = '<figure class="content-image-embed">';
                if ($src !== '') {
                    $figure .= '<img class="content-image-embed__image" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($altText, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
                } else {
                    $figure .= '<div class="content-image-embed__body"><i class="bi bi-image"></i><div class="content-image-embed__meta">Image preview unavailable</div></div>';
                }
                if ($caption !== '' || $altText !== '') {
                    $figure .= '<figcaption class="content-image-embed__caption">';
                    if ($caption !== '') {
                        $figure .= '<div class="content-image-embed__title">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    if ($altText !== '') {
                        $figure .= '<div class="content-image-embed__meta">' . htmlspecialchars($altText, ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    $figure .= '</figcaption>';
                }
                $figure .= '</figure>';
                $html[] = $figure;
                continue;
            }

            if (is_array($insert['contentReference'] ?? null)) {
                $reference = $insert['contentReference'];
                $referenceKey = trim((string) ($reference['referenceUuid'] ?? ''));
                $label = trim((string) ($reference['label'] ?? 'Reference'));
                $referenceNumber = $referenceKey !== '' && isset($referenceIndexByKey[$referenceKey])
                    ? $referenceIndexByKey[$referenceKey]
                    : ($label !== '' && isset($referenceIndexByTitle[mb_strtolower($label)]) ? $referenceIndexByTitle[mb_strtolower($label)] : null);
                $lineBuffer .= self::renderReferenceFootnote($referenceNumber, $label);
            }
        }

        $flushLine();
        $flushList();

        return implode('', $html);
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function plainText(array $content): string
    {
        $html = self::render($content);
        if ($html === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, mixed> $content
     * @return array{keys: list<string>, titles: list<string>}
     */
    public static function collectReferenceMentions(array $content): array
    {
        if ((string) ($content['format'] ?? '') !== 'quill_delta') {
            return ['keys' => [], 'titles' => []];
        }

        $delta = $content['delta'] ?? null;
        $ops = is_array($delta['ops'] ?? null) ? $delta['ops'] : [];
        $keys = [];
        $titles = [];

        foreach ($ops as $op) {
            if (!is_array($op) || !array_key_exists('insert', $op)) {
                continue;
            }

            $insert = $op['insert'];
            if (is_string($insert)) {
                if (preg_match_all('/\[Ref:\s*([^\]]+)\]/', $insert, $matches) > 0) {
                    foreach ($matches[1] as $match) {
                        $label = trim((string) $match);
                        if ($label !== '') {
                            $titles[] = mb_strtolower($label);
                        }
                    }
                }
                continue;
            }

            if (!is_array($insert) || !is_array($insert['contentReference'] ?? null)) {
                continue;
            }

            $reference = $insert['contentReference'];
            $key = trim((string) ($reference['referenceUuid'] ?? ''));
            $label = trim((string) ($reference['label'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
            if ($label !== '') {
                $titles[] = mb_strtolower($label);
            }
        }

        return [
            'keys' => array_values(array_unique($keys)),
            'titles' => array_values(array_unique($titles)),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function renderInline(string $text, array $attributes, array $referenceIndexByTitle = []): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        if ($html === '') {
            return $html;
        }

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

        return self::renderLegacyReferenceMarkers($html, $referenceIndexByTitle);
    }

    /**
     * @param array<string, int> $referenceIndexByTitle
     */
    private static function renderLegacyReferenceMarkers(string $html, array $referenceIndexByTitle = []): string
    {
        return (string) preg_replace_callback(
            '/\[Ref:\s*([^\]]+)\]/',
            static function (array $matches) use ($referenceIndexByTitle): string {
                $label = trim((string) ($matches[1] ?? 'Reference'));
                $referenceNumber = $label !== '' && isset($referenceIndexByTitle[mb_strtolower($label)]) ? $referenceIndexByTitle[mb_strtolower($label)] : null;

                return self::renderReferenceFootnote($referenceNumber, $label);
            },
            $html,
        );
    }

    private static function renderReferenceFootnote(?int $number, string $label): string
    {
        $safeLabel = htmlspecialchars($label !== '' ? $label : 'Reference', ENT_QUOTES, 'UTF-8');
        if ($number === null) {
            return '<sup class="content-footnote content-footnote--missing" title="' . $safeLabel . '">[?]</sup>';
        }

        $target = 'content-reference-' . $number;
        $backref = 'content-reference-inline-' . $number;

        return '<sup class="content-footnote" id="' . $backref . '"><a href="#' . $target . '" title="' . $safeLabel . '">[' . $number . ']</a></sup>';
    }
}
