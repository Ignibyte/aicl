<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnitEnum;

/** Filament page that provides an in-panel browser for architecture documentation files. */
/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class DocumentBrowser extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Architecture Docs';

    protected static ?string $title = 'Architecture Docs';

    protected static ?string $slug = 'documents';

    protected string $view = 'aicl::filament.pages.document-browser';

    /**
     * Currently-selected file path (relative to base_path).
     *
     * No `#[Url]` attribute — URL query-param binding would allow any path
     * through the property setter. Instead, use the public `selectFile()`
     * action, which validates against `isAllowedPath()` before assigning.
     * The `updatedFile()` hook is a belt-and-suspenders validator for any
     * other channel (test setters, Livewire internals) — rejects disallowed
     * paths by resetting to null.
     */
    public ?string $file = null;

    /**
     * Public action for selecting a document file.
     *
     * The only supported write path for `$file`. Validates the proposed path
     * resolves within the configured document allowlist before assigning;
     * silently ignores disallowed paths.
     */
    public function selectFile(string $path): void
    {
        if ($this->isAllowedPath(base_path($path))) {
            $this->file = $path;

            return;
        }

        $this->file = null;
    }

    /**
     * Livewire lifecycle hook — runs on any `$file` property update.
     *
     * Defense-in-depth: if `$file` is set via any channel other than
     * `selectFile()` (e.g., a test setter, a Livewire internal, a legacy
     * URL binding), this hook runs the allowlist validator and resets to
     * null on failure.
     */
    public function updatedFile(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! $this->isAllowedPath(base_path($value))) {
            $this->file = null;
        }
    }

    /**
     * Get configured document paths, including auto-discovered framework docs.
     *
     * @return array<array{label: string, path: string}>
     */
    public function getDocPaths(): array
    {
        $paths = config('aicl.docs.paths', [
            ['label' => 'Architecture', 'path' => '.claude/architecture'],
        ]);

        // Auto-discover the framework package docs directory.
        // Uses __DIR__ so the path resolves correctly whether the package
        // is installed as a path repo (packages/aicl/) or via Composer (vendor/aicl/aicl/).
        $packageDocsDir = dirname(__DIR__, 3).'/docs';

        if (is_dir($packageDocsDir)) {
            $realPackageDocs = (string) realpath($packageDocsDir);
            $basePath = base_path();
            $relativePath = ltrim(str_replace($basePath, '', $realPackageDocs), DIRECTORY_SEPARATOR);

            $paths[] = ['label' => 'Framework Docs', 'path' => $relativePath];
        }

        return $paths;
    }

    /**
     * Get all markdown files across configured paths (recursive).
     *
     * @return array<array{name: string, path: string, relative: string, group: string}>
     */
    public function getFiles(): array
    {
        $files = [];

        foreach ($this->getDocPaths() as $docPath) {
            $fullPath = base_path($docPath['path']);

            if (! is_dir($fullPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }

                $relativePath = str_replace($fullPath.'/', '', $file->getPathname());

                $files[] = [
                    'name' => basename($file->getFilename(), '.md'),
                    'path' => $file->getPathname(),
                    'relative' => $docPath['path'].'/'.$relativePath,
                    'group' => $docPath['label'],
                ];
            }
        }

        usort($files, fn ($a, $b) => strcmp($a['group'].$a['name'], $b['group'].$b['name']));

        return $files;
    }

    /**
     * Render the currently selected file as HTML.
     */
    public function getDocumentHtml(): string
    {
        if (! $this->file) {
            return '';
        }

        $fullPath = base_path($this->file);

        // Security: ensure the file is within configured paths
        if (! $this->isAllowedPath($fullPath)) {
            return '<p class="text-red-500">Access denied: file is outside configured document paths.</p>';
        }

        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            return '<p class="text-gray-500">File not found or not readable.</p>';
        }

        // nosemgrep: file-get-contents-url -- $fullPath has passed isAllowedPath() which resolves via realpath() and requires the path to live under config('aicl.docs.paths'). No URL scheme is accepted.
        return Str::markdown((string) file_get_contents($fullPath), ['html_input' => 'strip']);
    }

    /**
     * Check that a file path is within one of the configured doc paths.
     */
    protected function isAllowedPath(string $fullPath): bool
    {
        $realPath = realpath($fullPath);

        if ($realPath === false) {
            return false;
        }

        foreach ($this->getDocPaths() as $docPath) {
            $allowedDir = realpath(base_path($docPath['path']));

            if ($allowedDir !== false && str_starts_with($realPath, $allowedDir)) {
                return true;
            }
        }

        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }
}
