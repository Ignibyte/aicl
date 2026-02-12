<?php

namespace Aicl\Filament\Resources\RlmLessons\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RlmLessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Lesson Content')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('topic')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subtopic')
                            ->maxLength(255),
                    ]),
                    TextInput::make('summary')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    RichEditor::make('detail')
                        ->required()
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        TextInput::make('tags')
                            ->maxLength(255)
                            ->helperText('Comma-separated tags'),
                        TextInput::make('source')
                            ->maxLength(255)
                            ->helperText('e.g., failures.md, agent-discovery'),
                    ]),
                    TagsInput::make('context_tags')
                        ->suggestions([
                            'entity', 'entity:states', 'entity:uuid', 'entity:enums',
                            'service', 'filament:form', 'filament:table', 'filament:widget',
                            'testing:factory', 'testing:dusk', 'command', 'middleware',
                        ])
                        ->columnSpanFull(),
                ]),

            Section::make('Settings')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('confidence')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->default(1.0),
                        TextInput::make('view_count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ]),
                    Toggle::make('is_verified')
                        ->default(false),
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
