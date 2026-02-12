<?php

// PATTERN: Form schema in its own class for separation of concerns.
// PATTERN: Section comes from Filament\Schemas\Components (NOT Filament\Forms\Components).
// PATTERN: Grid comes from Filament\Schemas\Components (NOT Filament\Forms\Components).
// PATTERN: Form field components come from Filament\Forms\Components.

namespace Aicl\Filament\Resources\Projects\Schemas;

use Aicl\Enums\ProjectPriority;
use Aicl\States\Active;
use Aicl\States\Archived;
use Aicl\States\Completed;
use Aicl\States\Draft;
use Aicl\States\OnHold;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
// PATTERN: Layout components from Schemas namespace.
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    // PATTERN: Static configure() method receives and returns the Schema.
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // PATTERN: Group related fields into Sections.
                Section::make('Project Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('description')
                            ->columnSpanFull(),
                        // PATTERN: Use Grid for side-by-side fields.
                        Grid::make(2)->schema([
                            // PATTERN: State fields use getMorphClass() as option values.
                            Select::make('status')
                                ->options([
                                    Draft::getMorphClass() => 'Draft',
                                    Active::getMorphClass() => 'Active',
                                    OnHold::getMorphClass() => 'On Hold',
                                    Completed::getMorphClass() => 'Completed',
                                    Archived::getMorphClass() => 'Archived',
                                ])
                                ->default(Draft::getMorphClass())
                                ->required(),
                            // PATTERN: Enum fields map cases to labels.
                            Select::make('priority')
                                ->options(
                                    collect(ProjectPriority::cases())
                                        ->mapWithKeys(fn (ProjectPriority $p): array => [$p->value => $p->label()])
                                        ->all()
                                )
                                ->default(ProjectPriority::Medium->value)
                                ->required(),
                        ]),
                    ]),
                Section::make('Schedule & Budget')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('start_date'),
                            DatePicker::make('end_date')
                                ->after('start_date'),
                            // PATTERN: Money fields use numeric() + prefix('$').
                            TextInput::make('budget')
                                ->numeric()
                                ->prefix('$')
                                ->maxValue(999999999999),
                        ]),
                    ]),
                Section::make('Assignment')
                    ->schema([
                        // PATTERN: Relationship selects use ->relationship() for automatic handling.
                        Select::make('owner_id')
                            ->relationship('owner', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        // PATTERN: Many-to-many relationships use ->multiple().
                        Select::make('members')
                            ->relationship('members', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
            ]);
    }
}
