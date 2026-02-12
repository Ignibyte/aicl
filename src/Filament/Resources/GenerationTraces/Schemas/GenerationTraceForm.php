<?php

namespace Aicl\Filament\Resources\GenerationTraces\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GenerationTraceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Generation Context')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('entity_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('aicl_version')
                            ->label('AICL Version')
                            ->maxLength(255),
                        TextInput::make('laravel_version')
                            ->label('Laravel Version')
                            ->maxLength(255),
                    ]),
                    TextInput::make('project_hash')
                        ->maxLength(255)
                        ->helperText('SHA256 hash of originating project'),
                    Textarea::make('scaffolder_args')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    KeyValue::make('file_manifest')
                        ->label('File Manifest')
                        ->columnSpanFull(),
                ]),

            Section::make('Validation Results')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('structural_score')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('semantic_score')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('fix_iterations')
                            ->numeric()
                            ->default(0),
                        TextInput::make('pipeline_duration')
                            ->numeric()
                            ->suffix('seconds'),
                    ]),
                    Textarea::make('test_results')
                        ->rows(3)
                        ->columnSpanFull(),
                    KeyValue::make('fixes_applied')
                        ->columnSpanFull(),
                    KeyValue::make('agent_versions')
                        ->columnSpanFull(),
                ]),

            Section::make('Settings')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('is_processed')
                            ->default(false)
                            ->helperText('Marked when analyzed by pattern discovery'),
                        Select::make('owner_id')
                            ->relationship('owner', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                ]),
        ]);
    }
}
