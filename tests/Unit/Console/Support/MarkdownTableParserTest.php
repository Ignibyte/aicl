<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\MarkdownTableParser;
use PHPUnit\Framework\TestCase;

class MarkdownTableParserTest extends TestCase
{
    // ========================================================================
    // parseMarkdownTable()
    // ========================================================================

    public function test_parse_simple_table(): void
    {
        $table = <<<'MD'
| Name | Type | Description |
|------|------|-------------|
| title | string | The title |
| count | integer | Item count |
MD;

        $rows = MarkdownTableParser::parseMarkdownTable($table);

        $this->assertCount(2, $rows);
        $this->assertSame('title', $rows[0]['name']);
        $this->assertSame('string', $rows[0]['type']);
        $this->assertSame('The title', $rows[0]['description']);
        $this->assertSame('count', $rows[1]['name']);
        $this->assertSame('integer', $rows[1]['type']);
    }

    public function test_parse_table_with_alignment_markers(): void
    {
        $table = <<<'MD'
| Name | Value |
|:-----|------:|
| foo  | 42    |
MD;

        $rows = MarkdownTableParser::parseMarkdownTable($table);

        $this->assertCount(1, $rows);
        $this->assertSame('foo', $rows[0]['name']);
        $this->assertSame('42', $rows[0]['value']);
    }

    public function test_parse_table_headers_are_lowercased(): void
    {
        $table = <<<'MD'
| Field Name | Column TYPE |
|------------|-------------|
| title | string |
MD;

        $rows = MarkdownTableParser::parseMarkdownTable($table);

        $this->assertArrayHasKey('field name', $rows[0]);
        $this->assertArrayHasKey('column type', $rows[0]);
    }

    public function test_parse_table_with_empty_cells(): void
    {
        $table = <<<'MD'
| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| count | integer | nullable |
MD;

        $rows = MarkdownTableParser::parseMarkdownTable($table);

        $this->assertCount(2, $rows);
        $this->assertSame('', $rows[0]['modifiers']);
        $this->assertSame('nullable', $rows[1]['modifiers']);
    }

    public function test_parse_empty_content_returns_empty(): void
    {
        $rows = MarkdownTableParser::parseMarkdownTable('');

        $this->assertSame([], $rows);
    }

    public function test_parse_content_without_table_returns_empty(): void
    {
        $rows = MarkdownTableParser::parseMarkdownTable("Some plain text\nMore text");

        $this->assertSame([], $rows);
    }

    public function test_parse_table_skips_blank_lines(): void
    {
        $table = <<<'MD'

| Name | Type |
|------|------|

| foo | bar |

MD;

        $rows = MarkdownTableParser::parseMarkdownTable($table);

        $this->assertCount(1, $rows);
        $this->assertSame('foo', $rows[0]['name']);
    }

    public function test_parse_table_with_fewer_data_cells_than_headers(): void
    {
        $table = <<<'MD'
| Name | Type | Extra |
|------|------|-------|
| foo | bar |
MD;

        $rows = MarkdownTableParser::parseMarkdownTable($table);

        $this->assertCount(1, $rows);
        $this->assertSame('foo', $rows[0]['name']);
        $this->assertSame('bar', $rows[0]['type']);
        $this->assertSame('', $rows[0]['extra']);
    }

    // ========================================================================
    // splitSections()
    // ========================================================================

    public function test_split_sections_at_level_2(): void
    {
        $content = <<<'MD'
# MyEntity

A description paragraph.

## Fields

| Name | Type |
|------|------|
| title | string |

## States

draft → sent
MD;

        $sections = MarkdownTableParser::splitSections($content);

        $this->assertArrayHasKey('_name', $sections);
        $this->assertSame('MyEntity', $sections['_name']);
        $this->assertArrayHasKey('_header', $sections);
        $this->assertArrayHasKey('Fields', $sections);
        $this->assertArrayHasKey('States', $sections);
        $this->assertStringContainsString('title', $sections['Fields']);
        $this->assertStringContainsString('draft', $sections['States']);
    }

    public function test_split_sections_at_level_3(): void
    {
        $content = <<<'MD'
### Section A

Content A

### Section B

Content B
MD;

        $sections = MarkdownTableParser::splitSections($content, 3);

        $this->assertArrayHasKey('Section A', $sections);
        $this->assertArrayHasKey('Section B', $sections);
        $this->assertStringContainsString('Content A', $sections['Section A']);
        $this->assertStringContainsString('Content B', $sections['Section B']);
    }

    public function test_split_sections_preserves_header_content(): void
    {
        $content = <<<'MD'
# Entity

Some description here.

---

## Fields

stuff
MD;

        $sections = MarkdownTableParser::splitSections($content);

        $this->assertStringContainsString('Some description here.', $sections['_header']);
        $this->assertStringContainsString('---', $sections['_header']);
    }

    public function test_split_sections_with_no_headers(): void
    {
        $content = "Just some plain content\nWith multiple lines";

        $sections = MarkdownTableParser::splitSections($content);

        $this->assertArrayHasKey('_header', $sections);
        $this->assertStringContainsString('Just some plain content', $sections['_header']);
    }

    public function test_split_sections_empty_content(): void
    {
        $sections = MarkdownTableParser::splitSections('');

        $this->assertArrayHasKey('_header', $sections);
    }

    // ========================================================================
    // parseBulletList()
    // ========================================================================

    public function test_parse_bullet_list_with_dashes(): void
    {
        $content = <<<'MD'
- Item one
- Item two
- Item three
MD;

        $items = MarkdownTableParser::parseBulletList($content);

        $this->assertCount(3, $items);
        $this->assertSame('Item one', $items[0]);
        $this->assertSame('Item two', $items[1]);
        $this->assertSame('Item three', $items[2]);
    }

    public function test_parse_bullet_list_with_asterisks(): void
    {
        $content = <<<'MD'
* First item
* Second item
MD;

        $items = MarkdownTableParser::parseBulletList($content);

        $this->assertCount(2, $items);
        $this->assertSame('First item', $items[0]);
        $this->assertSame('Second item', $items[1]);
    }

    public function test_parse_bullet_list_ignores_non_bullet_lines(): void
    {
        $content = <<<'MD'
Some heading text

- Bullet item

More text that isn't a bullet
MD;

        $items = MarkdownTableParser::parseBulletList($content);

        $this->assertCount(1, $items);
        $this->assertSame('Bullet item', $items[0]);
    }

    public function test_parse_bullet_list_trims_whitespace(): void
    {
        $content = <<<'MD'
-   Indented item
-  Another item
MD;

        $items = MarkdownTableParser::parseBulletList($content);

        $this->assertCount(2, $items);
        $this->assertSame('Indented item', $items[0]);
        $this->assertSame('Another item', $items[1]);
    }

    public function test_parse_empty_bullet_list(): void
    {
        $items = MarkdownTableParser::parseBulletList('');

        $this->assertSame([], $items);
    }

    public function test_parse_bullet_list_mixed_markers(): void
    {
        $content = <<<'MD'
- Dash item
* Asterisk item
- Another dash
MD;

        $items = MarkdownTableParser::parseBulletList($content);

        $this->assertCount(3, $items);
        $this->assertSame('Dash item', $items[0]);
        $this->assertSame('Asterisk item', $items[1]);
        $this->assertSame('Another dash', $items[2]);
    }
}
