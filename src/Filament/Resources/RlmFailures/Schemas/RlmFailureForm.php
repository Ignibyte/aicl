<?php

namespace Aicl\Filament\Resources\RlmFailures\Schemas;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RlmFailureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Failure Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('failure_code')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('pattern_id')
                            ->label('Pattern ID')
                            ->maxLength(255),
                    ]),
                    Grid::make(3)->schema([
                        Select::make('category')
                            ->options(collect(FailureCategory::cases())->mapWithKeys(
                                fn (FailureCategory $case) => [$case->value => $case->label()]
                            ))
                            ->required()
                            ->searchable(),
                        TextInput::make('subcategory')
                            ->maxLength(255),
                        Select::make('severity')
                            ->options(collect(FailureSeverity::cases())->mapWithKeys(
                                fn (FailureSeverity $case) => [$case->value => $case->label()]
                            ))
                            ->required(),
                    ]),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        Textarea::make('root_cause')
                            ->rows(3),
                        Textarea::make('fix')
                            ->rows(3),
                    ]),
                    Textarea::make('preventive_rule')
                        ->rows(2)
                        ->columnSpanFull(),
                    KeyValue::make('entity_context')
                        ->columnSpanFull(),
                ]),

            Section::make('Tracking')
                ->schema([
                    Grid::make(3)->schema([
                        DateTimePicker::make('first_seen_at'),
                        DateTimePicker::make('last_seen_at'),
                        Select::make('status')
                            ->options([
                                'reported' => 'Reported',
                                'confirmed' => 'Confirmed',
                                'investigating' => 'Investigating',
                                'resolved' => 'Resolved',
                                'wont_fix' => 'Won\'t Fix',
                                'deprecated' => 'Deprecated',
                            ])
                            ->required(),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('aicl_version')
                            ->label('AICL Version')
                            ->maxLength(255),
                        TextInput::make('laravel_version')
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('Statistics')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('report_count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        TextInput::make('project_count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        TextInput::make('resolution_count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('resolution_rate')
                            ->numeric()
                            ->disabled(),
                        DateTimePicker::make('promoted_at')
                            ->disabled(),
                    ]),
                ])
                ->collapsed()
                ->collapsible(),

            Section::make('Settings')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('owner_id')
                            ->relationship('owner', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('scaffolding_fixed')
                            ->default(false),
                        Toggle::make('promoted_to_base')
                            ->default(false),
                    ]),
                ]),
        ]);
    }
}
