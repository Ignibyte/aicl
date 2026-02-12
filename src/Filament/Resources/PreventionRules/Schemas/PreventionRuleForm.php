<?php

namespace Aicl\Filament\Resources\PreventionRules\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PreventionRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rule Definition')
                ->schema([
                    Textarea::make('rule_text')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                    KeyValue::make('trigger_context')
                        ->helperText('JSON context that triggers this rule (e.g., has_states, field_types)')
                        ->columnSpanFull(),
                    Grid::make(3)->schema([
                        TextInput::make('confidence')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->default(0.0),
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher = more important'),
                        TextInput::make('applied_count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ]),
                    DateTimePicker::make('last_applied_at')
                        ->disabled(),
                ]),

            Section::make('Settings')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('rlm_failure_id')
                            ->relationship('failure', 'title')
                            ->searchable()
                            ->preload()
                            ->helperText('Optional — some rules are standalone'),
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
