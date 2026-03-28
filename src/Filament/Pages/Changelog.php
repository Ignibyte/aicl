<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages;

use Aicl\Services\VersionService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use UnitEnum;

/** Filament page that displays framework and project changelogs with tabbed navigation. */
class Changelog extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Changelog';

    protected static ?string $slug = 'changelog';

    protected string $view = 'aicl::filament.pages.changelog';

    public function getTitle(): string
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $versionService = app(VersionService::class);
        $framework = $versionService->frameworkVersion();
        $project = $versionService->projectVersion();

        $title = "Changelog — Framework v{$framework}";

        if ($project !== 'unknown') {
            $title .= " | Project v{$project}";
        }

        return $title;
        // @codeCoverageIgnoreEnd
    }

    public function getFrameworkChangelogHtml(): string
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $path = $this->frameworkChangelogPath();

        if ($path === null) {
            return '<p class="text-gray-500">No changelog found.</p>';
        }

        return $this->renderChangelog($path);
        // @codeCoverageIgnoreEnd
    }

    public function getProjectChangelogHtml(): string
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return $this->renderChangelog(base_path('CHANGELOG.md'));
        // @codeCoverageIgnoreEnd
    }

    public function hasProjectChangelog(): bool
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return file_exists(base_path('CHANGELOG.md'));
        // @codeCoverageIgnoreEnd
    }

    public function hasFrameworkChangelog(): bool
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return $this->frameworkChangelogPath() !== null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Resolve the framework changelog path.
     * Dev: project root. Shipped: vendor package directory.
     */
    private function frameworkChangelogPath(): ?string
    {
        // Dev environment: changelog at project root
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $devPath = base_path('CHANGELOG_FRAMEWORK.md');

        if (file_exists($devPath)) {
            return $devPath;
        }

        // Shipped projects: look inside the installed package
        $vendorPath = base_path('vendor/aicl/aicl/CHANGELOG_FRAMEWORK.md');

        if (file_exists($vendorPath)) {
            return $vendorPath;
        }

        return null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @deprecated Use getFrameworkChangelogHtml() instead.
     */
    public function getChangelogHtml(): string
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return $this->getFrameworkChangelogHtml();
        // @codeCoverageIgnoreEnd
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            // @codeCoverageIgnoreStart — Filament Livewire rendering
            return false;
            // @codeCoverageIgnoreEnd
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    private function renderChangelog(string $path): string
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        if (! file_exists($path)) {
            return '<p class="text-gray-500">No changelog found.</p>';
        }

        return Str::markdown((string) file_get_contents($path), ['html_input' => 'strip']);
        // @codeCoverageIgnoreEnd
    }
}
