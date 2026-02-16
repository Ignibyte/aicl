<?php

// PATTERN: Filament v4 Resource class — the central hub for admin CRUD.
// PATTERN: Separate form and table into their own classes for maintainability.
// PATTERN: Register global search attributes for the search bar.

namespace Aicl\Filament\Resources\Projects;

use Aicl\Filament\Resources\Projects\Pages\CreateProject;
use Aicl\Filament\Resources\Projects\Pages\EditProject;
use Aicl\Filament\Resources\Projects\Pages\ListProjects;
use Aicl\Filament\Resources\Projects\Pages\ViewProject;
use Aicl\Filament\Resources\Projects\Schemas\ProjectForm;
use Aicl\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
// PATTERN: Use Heroicon constants (not strings) for navigation icons.
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    // PATTERN: When resource belongs to a $navigationGroup, set $navigationIcon = null.
    // The group icon is defined in AdminPanelProvider via NavigationGroup::make()->icon().
    // Only set a Heroicon constant here if the resource does NOT belong to any group.
    protected static string|BackedEnum|null $navigationIcon = null;

    // PATTERN: $navigationGroup type is string|UnitEnum|null (not ?string).
    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 1;

    // PATTERN: recordTitleAttribute enables global search.
    protected static ?string $recordTitleAttribute = 'name';

    // PATTERN: Global search configuration.
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        /** @var Project $record */
        return $record->name;
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        /** @var Project $record */
        return [
            'Status' => (string) $record->status,
            'Priority' => $record->priority->value,
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    // PATTERN: Delegate form/table to separate classes.
    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    // PATTERN: Four standard pages: list, create, view, edit.
    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
