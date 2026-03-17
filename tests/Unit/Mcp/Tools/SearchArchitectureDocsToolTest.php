<?php

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\SearchArchitectureDocsTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\TestCase;

class SearchArchitectureDocsToolTest extends TestCase
{
    protected SearchArchitectureDocsTool $tool;

    protected string $tempDocsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDocsPath = sys_get_temp_dir().'/aicl-arch-docs-test-'.uniqid();
        mkdir($this->tempDocsPath, 0755, true);

        $this->tool = new SearchArchitectureDocsTool($this->tempDocsPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDocsPath);

        parent::tearDown();
    }

    // ─── Tool metadata ────────────────────────────────────

    public function test_name_returns_expected_value(): void
    {
        $this->assertSame('search-architecture-docs', $this->tool->name());
    }

    public function test_description_is_set(): void
    {
        $this->assertNotEmpty($this->tool->description());
    }

    // ─── List mode ────────────────────────────────────────

    public function test_list_mode_returns_empty_when_no_docs(): void
    {
        $data = $this->callAndDecode([]);

        $this->assertSame(0, $data['total']);
        $this->assertEmpty($data['docs']);
    }

    public function test_list_mode_returns_discovered_docs(): void
    {
        $this->writeDoc('redis.md', "# Redis Configuration\n\n## Overview\n\nRedis setup.\n\n## Connection\n\nDetails.");
        $this->writeDoc('auth.md', "# Authentication\n\n## OAuth2\n\nOAuth details.\n\n## MFA\n\nMFA details.");

        $data = $this->callAndDecode([]);

        $this->assertSame(2, $data['total']);

        $slugs = array_column($data['docs'], 'slug');
        $this->assertContains('auth', $slugs);
        $this->assertContains('redis', $slugs);
    }

    public function test_list_mode_skips_readme(): void
    {
        $this->writeDoc('README.md', "# Docs Index\n\nThis is the readme.");
        $this->writeDoc('redis.md', "# Redis\n\nContent.");

        $data = $this->callAndDecode([]);

        $this->assertSame(1, $data['total']);

        $slugs = array_column($data['docs'], 'slug');
        $this->assertNotContains('README', $slugs);
    }

    public function test_list_mode_extracts_sections(): void
    {
        $this->writeDoc('redis.md', "# Redis\n\n## Overview\n\nContent.\n\n## Configuration\n\nMore content.");

        $data = $this->callAndDecode([]);

        $this->assertSame(['Overview', 'Configuration'], $data['docs'][0]['sections']);
    }

    // ─── Fetch by slug ────────────────────────────────────

    public function test_fetch_doc_by_slug(): void
    {
        $this->writeDoc('redis.md', "# Redis Configuration\n\n## Overview\n\nRedis content here.");

        $data = $this->callAndDecode(['doc' => 'redis']);

        $this->assertSame('redis', $data['slug']);
        $this->assertSame('Redis Configuration', $data['title']);
        $this->assertArrayHasKey('content', $data);
        $this->assertStringContainsString('Redis content here', $data['content']);
    }

    public function test_fetch_doc_with_section_filter(): void
    {
        $this->writeDoc('redis.md', "# Redis\n\n## Overview\n\nGeneral info.\n\n## Configuration\n\nConfig details here.\n\n## Gotchas\n\nWatch out.");

        $data = $this->callAndDecode(['doc' => 'redis', 'section' => 'Configuration']);

        $this->assertSame('Configuration', $data['section']);
        $this->assertStringContainsString('Config details here', $data['content']);
        $this->assertStringNotContainsString('General info', $data['content']);
    }

    public function test_fetch_doc_returns_error_for_missing_slug(): void
    {
        $text = $this->callAndDecodeText(['doc' => 'nonexistent']);

        $this->assertStringContainsString('not found', $text);
    }

    public function test_fetch_doc_returns_error_for_missing_section(): void
    {
        $this->writeDoc('redis.md', "# Redis\n\n## Overview\n\nContent.");

        $text = $this->callAndDecodeText(['doc' => 'redis', 'section' => 'Nonexistent']);

        $this->assertStringContainsString('not found', $text);
        $this->assertStringContainsString('Overview', $text);
    }

    // ─── Search mode ──────────────────────────────────────

    public function test_search_by_query_finds_matching_docs(): void
    {
        $this->writeDoc('redis.md', "# Redis Configuration\n\n## Overview\n\nRedis caching setup.");
        $this->writeDoc('auth.md', "# Authentication\n\n## OAuth2\n\nOAuth2 flow details.");

        $data = $this->callAndDecode(['query' => 'redis']);

        $this->assertSame(1, $data['total']);
        $this->assertSame('redis', $data['docs'][0]['slug']);
    }

    public function test_search_ranks_title_matches_higher(): void
    {
        $this->writeDoc('redis.md', "# Redis Configuration\n\nSome content.");
        $this->writeDoc('auth.md', "# Authentication\n\nUses redis for sessions.");

        $data = $this->callAndDecode(['query' => 'redis']);

        $this->assertSame('redis', $data['docs'][0]['slug']);
    }

    public function test_search_respects_limit(): void
    {
        $this->writeDoc('redis.md', "# Redis\n\nContent about config.");
        $this->writeDoc('auth.md', "# Auth Config\n\nContent about config.");

        $data = $this->callAndDecode(['query' => 'config', 'limit' => 1]);

        $this->assertSame(1, $data['showing']);
        $this->assertSame(2, $data['total']);
    }

    public function test_search_returns_snippets(): void
    {
        $this->writeDoc('redis.md', "# Redis\n\n## Overview\n\nRedis is used for caching and session storage.");

        $data = $this->callAndDecode(['query' => 'caching']);

        $this->assertArrayHasKey('snippet', $data['docs'][0]);
        $this->assertNotEmpty($data['docs'][0]['snippet']);
    }

    public function test_search_returns_empty_for_no_matches(): void
    {
        $this->writeDoc('redis.md', "# Redis\n\nContent.");

        $data = $this->callAndDecode(['query' => 'kubernetes']);

        $this->assertSame(0, $data['total']);
        $this->assertEmpty($data['docs']);
    }

    // ─── Edge cases ───────────────────────────────────────

    public function test_discovers_nested_docs(): void
    {
        mkdir($this->tempDocsPath.'/subdir', 0755, true);
        $this->writeDoc('subdir/nested.md', "# Nested Doc\n\nContent.");

        $data = $this->callAndDecode([]);

        $slugs = array_column($data['docs'], 'slug');
        $this->assertContains('subdir/nested', $slugs);
    }

    public function test_handles_missing_directory_gracefully(): void
    {
        $tool = new SearchArchitectureDocsTool('/tmp/nonexistent-aicl-docs-'.uniqid());

        $data = $this->decodeJson($tool->handle(new Request([])));

        $this->assertSame(0, $data['total']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('No docs/architecture/', $data['message']);
    }

    public function test_tool_is_registered_in_boost_config(): void
    {
        $include = config('boost.mcp.tools.include', []);

        $this->assertContains(SearchArchitectureDocsTool::class, $include);
    }

    // ─── Helpers ──────────────────────────────────────────

    /**
     * Call the tool and decode the JSON response.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function callAndDecode(array $arguments): array
    {
        return $this->decodeJson($this->tool->handle(new Request($arguments)));
    }

    /**
     * Call the tool and return the raw response text.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function callAndDecodeText(array $arguments): string
    {
        return $this->extractText($this->tool->handle(new Request($arguments)));
    }

    /**
     * Decode a JSON Response into an array.
     *
     * @return array<string, mixed>
     */
    protected function decodeJson(Response $response): array
    {
        return json_decode($this->extractText($response), true) ?? [];
    }

    /**
     * Extract the raw text from a Response via reflection.
     */
    protected function extractText(Response $response): string
    {
        $reflection = new \ReflectionClass($response);
        $contentProp = $reflection->getProperty('content');
        $content = $contentProp->getValue($response);

        $textReflection = new \ReflectionClass($content);
        $textProp = $textReflection->getProperty('text');

        return $textProp->getValue($content);
    }

    protected function writeDoc(string $filename, string $content): void
    {
        file_put_contents($this->tempDocsPath.'/'.$filename, $content);
    }

    protected function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
