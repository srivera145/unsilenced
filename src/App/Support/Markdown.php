<?php

namespace Keel\App\Support;

/**
 * The small Markdown subset admin-edited pages are written in.
 *
 *   ## Heading / ### Heading
 *   - bullet          1. numbered
 *   **bold**          [link text](https://example.org)
 *
 * Everything is escaped first and markup is added afterwards, so no HTML an
 * admin types ever reaches the page. Links accept only https, http, mailto, tel
 * and same-site paths; anything else renders as plain text. Off-site links
 * carry rel="noopener noreferrer" so the destination never learns the visitor
 * came from here.
 */
class Markdown
{
    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        if ($markdown === '') {
            return '';
        }

        $html = [];
        $paragraph = [];
        $list = null; // ['tag' => 'ul'|'ol', 'items' => []]

        $flushParagraph = static function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . self::inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };

        $flushList = static function () use (&$list, &$html): void {
            if ($list !== null) {
                $items = array_map(static fn (string $item): string => '<li>' . self::inline($item) . '</li>', $list['items']);
                $html[] = '<' . $list['tag'] . '>' . implode('', $items) . '</' . $list['tag'] . '>';
                $list = null;
            }
        };

        foreach (explode("\n", $markdown) as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                $flushParagraph();
                $flushList();
                continue;
            }

            if (preg_match('/^(#{2,3})\s+(.+)$/', $line, $m)) {
                $flushParagraph();
                $flushList();
                $level = strlen($m[1]);
                $html[] = "<h{$level}>" . self::inline($m[2]) . "</h{$level}>";
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $m) || preg_match('/^\d+[.)]\s+(.+)$/', $line, $m)) {
                $tag = preg_match('/^\d/', $line) ? 'ol' : 'ul';
                $flushParagraph();

                if ($list !== null && $list['tag'] !== $tag) {
                    $flushList();
                }

                $list ??= ['tag' => $tag, 'items' => []];
                $list['items'][] = $m[1];
                continue;
            }

            // A continuation line under a list item belongs to that item.
            if ($list !== null && preg_match('/^\s{2,}\S/', $rawLine)) {
                $last = array_key_last($list['items']);
                $list['items'][$last] .= ' ' . $line;
                continue;
            }

            $flushList();
            $paragraph[] = $line;
        }

        $flushParagraph();
        $flushList();

        return implode("\n", $html);
    }

    /** Plain text with all markup removed, for meta descriptions and llms.txt. */
    public static function toText(string $markdown): string
    {
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $markdown) ?? $markdown;
        $text = preg_replace('/^#{2,3}\s+|^[-*]\s+|^\d+[.)]\s+|\*\*/m', '', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            static function (array $m): string {
                $label = $m[1];
                $href = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');

                if (!self::isAllowedHref($href)) {
                    return $label;
                }

                $external = !str_starts_with($href, '/');
                $rel = $external && preg_match('#^https?://#i', $href) ? ' rel="noopener noreferrer"' : '';

                return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $rel . '>' . $label . '</a>';
            },
            $escaped
        ) ?? $escaped;

        return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
    }

    private static function isAllowedHref(string $href): bool
    {
        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return true;
        }

        return (bool) preg_match('#^(https?://[^\s/$.?\#].\S*|mailto:\S+@\S+|tel:\+?[0-9\-]+)$#i', $href);
    }
}
