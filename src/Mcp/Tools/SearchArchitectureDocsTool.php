<?php

declare(strict_types=1);

namespace Aicl\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * MCP tool for searching project architecture documentation.
 *
 * Searches markdown files in docs/architecture/ — persistent architectural
 * documentation that captures integration details, service designs, middleware
 * flows, and technical decisions that future agents need to understand.
 *
 * Three modes:
 *   1. List all docs (no params)
 *   2. Search by query (keyword search across title, headers, content)
 *   3. Fetch a specific doc by slug, optionally filtered to a section
 *
 * Registered into Laravel Boost via AiclServiceProvider so every AICL
 * project exposes its architecture docs through MCP automatically.
 */
#[IsReadOnly]
class SearchArchitectureDocsTool extends Tool
{
    protected string $name = 'search-architecture-docs';

    protected string $description = 'Search project architecture documentation in docs/architecture/. Three modes: (1) search by query, (2) fetch a specific doc/section by slug, (3) list all docs (no params). These are project-level architectural decisions, integration guides, and service documentation.';

    public function __construct(
        protected ?string $docsPath = null,
    ) {}

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Keyword search across all doc titles, headers, and content'),
            'doc' => $schema->string()
                ->description('Fetch a specific doc by slug (filename without .md extension, e.g. "redis", "swoole-octane")'),
            'section' => $schema->string()
                ->description('Filter to a specific H2 section within a doc (requires doc param)'),
            'limit' => $schema->integer()
                ->description('Max search results (default 3, max 10)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $docsPath = $this->docsPath ?? base_path('docs/architecture');

        if (! is_dir($docsPath)) {
            return Response::json([
                'docs' => [],
                'total' => 0,
                'message' => 'No docs/architecture/ directory found. Create it to store project architecture documentation.',
            ]);
        }

        $slug = (string) $request->string('doc');
        $query = (string) $request->string('query');
        $section = (string) $request->string('section');
        $limit = min($request->integer('limit', 3), 10);

        // Mode 2: Fetch specific doc by slug
        if ($slug !== '') {
            return $this->fetchDoc($docsPath, $slug, $section ?: null);
        }

        $docs = $this->discoverDocs($docsPath);

        if (empty($docs)) {
            return Response::json([
                'docs' => [],
                'total' => 0,
                'message' => 'No markdown files found in docs/architecture/.',
            ]);
        }

        // Mode 1: Search by query
        if ($query !== '') {
            return $this->searchDocs($docs, $query, $limit);
        }

        // Mode 3: List all docs
        return Response::json([
            'docs' => array_map(fn (array $doc): array => [
                'slug' => $doc['slug'],
                'title' => $doc['title'],
                'sections' => $doc['sections'],
            ], $docs),
            'total' => count($docs),
        ]);
    }

    /**
     * Discover all markdown files in the docs directory.
     *
     * @return array<int, array{slug: string, title: string, sections: array<string>, path: string, content: string}>
     */
    protected function discoverDocs(string $docsPath): array
    {
        $docs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($docsPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = (string) str_replace($docsPath.'/', '', $file->getPathname());
            $slug = (string) str_replace('.md', '', $relativePath);

            // Skip the README
            if (Str::lower($slug) === 'readme') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if ($content === false) {
                continue;
            }

            $title = $this->extractTitle($content, $slug);
            $sections = $this->extractSections($content);

            $docs[] = [
                'slug' => $slug,
                'title' => $title,
                'sections' => $sections,
                'path' => $file->getPathname(),
                'content' => $content,
            ];
        }

        usort($docs, fn (array $a, array $b): int => $a['title'] <=> $b['title']);

        return $docs;
    }

    /**
     * Fetch a specific doc by slug, optionally filtered to a section.
     */
    protected function fetchDoc(string $docsPath, string $slug, ?string $section): Response
    {
        $filePath = $docsPath.'/'.ltrim($slug, '/').'.md';

        if (! file_exists($filePath)) {
            return Response::error("Doc not found: {$slug}. Use this tool with no parameters to list available docs.");
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return Response::error("Failed to read doc: {$slug}");
        }

        $title = $this->extractTitle($content, $slug);

        if ($section) {
            $sectionContent = $this->extractSectionContent($content, $section);

            if ($sectionContent === null) {
                $availableSections = $this->extractSections($content);

                return Response::error(
                    "Section \"{$section}\" not found in {$slug}. Available sections: ".implode(', ', $availableSections)
                );
            }

            return Response::json([
                'slug' => $slug,
                'title' => $title,
                'section' => $section,
                'content' => $sectionContent,
            ]);
        }

        return Response::json([
            'slug' => $slug,
            'title' => $title,
            'sections' => $this->extractSections($content),
            'content' => $content,
        ]);
    }

    /**
     * Search docs by keyword query, ranking by relevance.
     *
     * @param  array<int, array{slug: string, title: string, sections: array<string>, path: string, content: string}>  $docs
     */
    protected function searchDocs(array $docs, string $query, int $limit): Response
    {
        $terms = array_filter(
            preg_split('/\s+/', Str::lower($query)) ?: [],
        );

        if (empty($terms)) {
            return Response::error('Search query is empty.');
        }

        $scored = [];

        foreach ($docs as $doc) {
            $score = $this->scoreDocs($doc, $terms);

            if ($score > 0) {
                $scored[] = [
                    'doc' => $doc,
                    'score' => $score,
                ];
            }
        }

        // Sort by score descending
        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $results = array_slice($scored, 0, $limit);

        return Response::json([
            'docs' => array_map(fn (array $item): array => [
                'slug' => $item['doc']['slug'],
                'title' => $item['doc']['title'],
                'sections' => $item['doc']['sections'],
                'snippet' => $this->extractSnippet($item['doc']['content'], $terms),
            ], $results),
            'total' => count($scored),
            'showing' => count($results),
        ]);
    }

    /**
     * Score a doc against search terms. Higher = more relevant.
     *
     * @param  array{slug: string, title: string, sections: array<string>, content: string}  $doc
     * @param  array<string>  $terms
     */
    protected function scoreDocs(array $doc, array $terms): int
    {
        $score = 0;
        $titleLower = Str::lower($doc['title']);
        $slugLower = Str::lower($doc['slug']);
        $contentLower = Str::lower($doc['content']);

        foreach ($terms as $term) {
            // Title match (highest weight)
            if (str_contains($titleLower, $term)) {
                $score += 10;
            }

            // Slug match
            if (str_contains($slugLower, $term)) {
                $score += 8;
            }

            // Section header match
            foreach ($doc['sections'] as $section) {
                if (str_contains(Str::lower($section), $term)) {
                    $score += 5;
                }
            }

            // Content match (count occurrences, cap at 5)
            $occurrences = substr_count($contentLower, $term);

            if ($occurrences > 0) {
                $score += min($occurrences, 5);
            }
        }

        return $score;
    }

    /**
     * Extract the H1 title from markdown content.
     */
    protected function extractTitle(string $content, string $fallbackSlug): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return Str::headline($fallbackSlug);
    }

    /**
     * Extract H2 section names from markdown content.
     *
     * @return array<string>
     */
    protected function extractSections(string $content): array
    {
        preg_match_all('/^##\s+(.+)$/m', $content, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * Extract the content of a specific H2 section.
     */
    protected function extractSectionContent(string $content, string $sectionName): ?string
    {
        $pattern = '/^##\s+'.preg_quote($sectionName, '/').'$(.*?)(?=^##\s|\z)/ms';

        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[0]);
        }

        // Try case-insensitive match
        $pattern = '/^##\s+'.preg_quote($sectionName, '/').'$(.*?)(?=^##\s|\z)/msi';

        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }

    /**
     * Extract a relevant snippet around the first matching term.
     *
     * @param  array<string>  $terms
     */
    protected function extractSnippet(string $content, array $terms, int $contextChars = 200): string
    {
        $contentLower = Str::lower($content);

        foreach ($terms as $term) {
            $pos = strpos($contentLower, $term);

            if ($pos !== false) {
                $start = max(0, $pos - $contextChars);
                $length = strlen($term) + ($contextChars * 2);
                $snippet = substr($content, $start, $length);

                // Clean up to word boundaries
                if ($start > 0) {
                    $snippet = '...'.substr($snippet, (int) strpos($snippet, ' ') + 1);
                }

                if ($start + $length < strlen($content)) {
                    $lastSpace = strrpos($snippet, ' ');

                    if ($lastSpace !== false) {
                        $snippet = substr($snippet, 0, $lastSpace).'...';
                    }
                }

                return $snippet;
            }
        }

        // No match found, return beginning of content
        return Str::limit($content, $contextChars * 2);
    }
}
