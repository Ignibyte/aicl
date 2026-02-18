<?php

namespace Aicl\Filament\Pages;

use Aicl\Services\VersionService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use UnitEnum;

class Changelog extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Changelog';

    protected static ?string $slug = 'changelog';

    protected string $view = 'aicl::filament.pages.changelog';

    public function getTitle(): string
    {
        $versionService = app(VersionService::class);
        $framework = $versionService->frameworkVersion();
        $project = $versionService->projectVersion();

        $title = "Changelog — Framework v{$framework}";

        if ($project !== 'unknown') {
            $title .= " | Project v{$project}";
        }

        return $title;
    }

    public function getFrameworkChangelogHtml(): string
    {
        return $this->renderChangelog(base_path('CHANGELOG_FRAMEWORK.md'));
    }

    public function getProjectChangelogHtml(): string
    {
        return $this->renderChangelog(base_path('CHANGELOG.md'));
    }

    public function hasProjectChangelog(): bool
    {
        return file_exists(base_path('CHANGELOG.md'));
    }

    public function hasFrameworkChangelog(): bool
    {
        return file_exists(base_path('CHANGELOG_FRAMEWORK.md'));
    }

    /**
     * @deprecated Use getFrameworkChangelogHtml() instead.
     */
    public function getChangelogHtml(): string
    {
        return $this->getFrameworkChangelogHtml();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    private function renderChangelog(string $path): string
    {
        if (! file_exists($path)) {
            return '<p class="text-gray-500">No changelog found.</p>';
        }

        return Str::markdown(file_get_contents($path), ['html_input' => 'strip']);
    }
}
