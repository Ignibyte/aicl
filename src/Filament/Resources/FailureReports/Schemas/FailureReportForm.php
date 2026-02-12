<?php

namespace Aicl\Filament\Resources\FailureReports\Schemas;

use Aicl\Enums\ResolutionMethod;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FailureReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Failure Context')
                ->schema([
                    Select::make('rlm_failure_id')
                        ->relationship('failure', 'title')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->label('RLM Failure'),
                    Grid::make(2)->schema([
                        TextInput::make('project_hash')
                            ->required()
                            ->maxLength(255)
                            ->label('Project Hash'),
                        TextInput::make('entity_name')
                            ->required()
                            ->maxLength(255),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('phase')
                            ->maxLength(255),
                        TextInput::make('agent')
                            ->maxLength(255),
                    ]),
                    KeyValue::make('scaffolder_args')
                        ->label('Scaffolder Arguments'),
                    DateTimePicker::make('reported_at')
                        ->required()
                        ->default(now()),
                ]),

            Section::make('Resolution')
                ->schema([
                    Toggle::make('resolved')
                        ->default(false)
                        ->reactive(),
                    Select::make('resolution_method')
                        ->options(ResolutionMethod::class)
                        ->label('Resolution Method'),
                    Textarea::make('resolution_notes')
                        ->rows(3)
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        TextInput::make('time_to_resolve')
                            ->numeric()
                            ->suffix('minutes')
                            ->label('Time to Resolve'),
                        DateTimePicker::make('resolved_at'),
                    ]),
                ]),

            Section::make('Settings')
                ->schema([
                    Select::make('owner_id')
                        ->relationship('owner', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Toggle::make('is_active')
                        ->default(true),
                ]),
        ]);
    }
}
