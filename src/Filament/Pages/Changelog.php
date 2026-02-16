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
        $version = app(VersionService::class)->current();

        return "Changelog — v{$version}";
    }

    public function getChangelogHtml(): string
    {
        $path = base_path('CHANGELOG_FRAMEWORK.md');

        if (! file_exists($path)) {
            return '<p class="text-gray-500">No changelog found.</p>';
        }

        return Str::markdown(file_get_contents($path), ['html_input' => 'strip']);
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
