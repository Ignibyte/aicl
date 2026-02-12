<?php

namespace Aicl\Filament\Resources\RlmPatterns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RlmPatternForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pattern Definition')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('target')
                            ->required()
                            ->options([
                                'model' => 'Model',
                                'factory' => 'Factory',
                                'migration' => 'Migration',
                                'seeder' => 'Seeder',
                                'policy' => 'Policy',
                                'observer' => 'Observer',
                                'filament' => 'Filament Resource',
                                'form' => 'Form',
                                'table' => 'Table',
                                'controller' => 'Controller',
                                'test' => 'Test',
                                'exporter' => 'Exporter',
                            ])
                            ->searchable(),
                    ]),
                    Textarea::make('description')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('check_regex')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    Grid::make(3)->schema([
                        Select::make('severity')
                            ->required()
                            ->options([
                                'error' => 'Error',
                                'warning' => 'Warning',
                                'info' => 'Info',
                            ]),
                        TextInput::make('weight')
                            ->numeric()
                            ->default(1.0)
                            ->minValue(0)
                            ->maxValue(10)
                            ->step(0.01),
                        Select::make('category')
                            ->required()
                            ->options([
                                'structural' => 'Structural',
                                'naming' => 'Naming',
                                'security' => 'Security',
                                'performance' => 'Performance',
                                'convention' => 'Convention',
                            ])
                            ->searchable(),
                    ]),
                    KeyValue::make('applies_when')
                        ->columnSpanFull(),
                ]),

            Section::make('Settings')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('source')
                            ->required()
                            ->options([
                                'base' => 'Base',
                                'discovered' => 'Discovered',
                                'manual' => 'Manual',
                            ]),
                        Select::make('owner_id')
                            ->relationship('owner', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                    ]),
                    Toggle::make('is_active')
                        ->default(true),
                ]),

            Section::make('Statistics')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('pass_count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        TextInput::make('fail_count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        DateTimePicker::make('last_evaluated_at')
                            ->disabled(),
                    ]),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }
}
