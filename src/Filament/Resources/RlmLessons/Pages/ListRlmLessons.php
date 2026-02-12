<?php

namespace Aicl\Filament\Resources\RlmLessons\Pages;

use Aicl\Filament\Resources\RlmLessons\RlmLessonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRlmLessons extends ListRecords
{
    protected static string $resource = RlmLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
