<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Static utility for parsing structured Markdown elements.
 *
 * Extracts tables, sections, and bullet lists from Markdown content.
 * Used by SpecFileParser and future spec parsers (ToolSpecParser, PermissionSpecParser).
 */
class MarkdownTableParser
{
    /**
     * Parse a Markdown pipe-delimited table into an array of associative rows.
     *
     * Headers are lowercased and used as keys. Separator rows (---|---) are skipped.
     *
     * @return array<int, array<string, string>>
     */
    public static function parseMarkdownTable(string $content): array
    {
        $lines = explode("\n", trim($content));
        $rows = [];
        $headers = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '|')) {
                continue;
            }

            // Skip separator rows (---|---|---)
            if (preg_match('/^\|?[\s:|\-]+\|?$/', $line)) {
                continue;
            }

            $cells = array_map(
                fn (string $cell): string => trim($cell),
                explode('|', trim($line, '|'))
            );

            if (empty($headers)) {
                $headers = array_map(
                    fn (string $h): string => strtolower(trim($h)),
                    $cells
                );

                continue;
            }

            $row = [];

            foreach ($headers as $i => $header) {
                $row[$header] = $cells[$i] ?? '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Split Markdown content into sections keyed by header name.
     *
     * Level 1 headers (# Name) are stored under the '_name' key.
     * Content before any header is stored under '_header'.
     * Level 2+ headers (## Section) are split based on the $level parameter.
     *
     * @return array<string, string>
     */
    public static function splitSections(string $content, int $level = 2): array
    {
        $sections = [];
        $lines = explode("\n", $content);
        $currentSection = '_header';
        $currentContent = [];
        $headerPattern = '/^'.str_repeat('#', $level).' (.+)$/';

        foreach ($lines as $line) {
            if (preg_match($headerPattern, $line, $matches)) {
                $sections[$currentSection] = implode("\n", $currentContent);
                $currentSection = trim($matches[1]);
                $currentContent = [];
            } elseif (preg_match('/^# (.+)$/', $line, $matches) && $currentSection === '_header') {
                $sections['_name'] = trim($matches[1]);
                $currentContent = [];
            } else {
                $currentContent[] = $line;
            }
        }

        $sections[$currentSection] = implode("\n", $currentContent);

        return $sections;
    }

    /**
     * Parse a bullet list from Markdown content into an array of strings.
     *
     * Supports both `-` and `*` bullet markers.
     *
     * @return array<int, string>
     */
    public static function parseBulletList(string $content): array
    {
        $items = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $items[] = trim($matches[1]);
            }
        }

        return $items;
    }
}
