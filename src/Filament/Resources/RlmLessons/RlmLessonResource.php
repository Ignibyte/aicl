<?php

namespace Aicl\Filament\Resources\RlmLessons;

use Aicl\Filament\Resources\RlmLessons\Pages\CreateRlmLesson;
use Aicl\Filament\Resources\RlmLessons\Pages\EditRlmLesson;
use Aicl\Filament\Resources\RlmLessons\Pages\ListRlmLessons;
use Aicl\Filament\Resources\RlmLessons\Pages\ViewRlmLesson;
use Aicl\Filament\Resources\RlmLessons\Schemas\RlmLessonForm;
use Aicl\Filament\Resources\RlmLessons\Tables\RlmLessonsTable;
use Aicl\Models\RlmLesson;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RlmLessonResource extends Resource
{
    protected static ?string $model = RlmLesson::class;

    protected static string|UnitEnum|null $navigationGroup = 'RLM Hub';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'summary';

    public static function form(Schema $schema): Schema
    {
        return RlmLessonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RlmLessonsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRlmLessons::route('/'),
            'create' => CreateRlmLesson::route('/create'),
            'view' => ViewRlmLesson::route('/{record}'),
            'edit' => EditRlmLesson::route('/{record}/edit'),
        ];
    }
}
