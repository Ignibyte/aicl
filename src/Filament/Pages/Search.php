<?php

namespace Aicl\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Full-page search. Entity types are discovered from registered Filament resources.
 *
 * @property Collection $results
 */
class Search extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Search';

    protected static ?string $title = 'Search';

    protected static ?string $slug = 'search';

    protected string $view = 'aicl::filament.pages.search';

    public string $query = '';

    public ?string $entityType = null;

    public function updatedQuery(): void
    {
        unset($this->results);
    }

    public function updatedEntityType(): void
    {
        unset($this->results);
    }

    #[Computed]
    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        // Entity-specific search is added by the client application.
        // Each registered entity's Filament resource provides global search
        // via getGloballySearchableAttributes(). This page provides a
        // full-page search interface that can be extended.
        return collect();
    }

    /**
     * @return array<string, string>
     */
    public function getEntityTypes(): array
    {
        // Client applications add entity types here after generating entities.
        return [
            '' => 'All Types',
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
