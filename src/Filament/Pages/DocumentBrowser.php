<?php

namespace Aicl\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use UnitEnum;

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

    #[Url]
    public ?string $file = null;

    /**
     * Get configured document paths.
     *
     * @return array<array{label: string, path: string}>
     */
    public function getDocPaths(): array
    {
        return config('aicl.docs.paths', [
            ['label' => 'Architecture', 'path' => '.claude/architecture'],
        ]);
    }

    /**
     * Get all markdown files across configured paths.
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

            $mdFiles = glob($fullPath.'/*.md');

            if ($mdFiles === false) {
                continue;
            }

            foreach ($mdFiles as $mdFile) {
                $files[] = [
                    'name' => basename($mdFile, '.md'),
                    'path' => $mdFile,
                    'relative' => $docPath['path'].'/'.basename($mdFile),
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

        return Str::markdown(file_get_contents($fullPath), ['html_input' => 'strip']);
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
