<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Models\RlmLesson;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class RecentRlmLessonsWidget extends TableWidget
{
    use PausesWhenHidden;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Table will refresh on next poll
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most Viewed Lessons')
            ->query(RlmLesson::query()->where('is_active', true)->orderBy('view_count', 'desc')->limit(5))
            ->columns([
                TextColumn::make('topic')->badge()->weight('bold'),
                TextColumn::make('summary')->limit(50),
                TextColumn::make('view_count')->numeric()->label('Views'),
                IconColumn::make('is_verified')->boolean()->label('Verified'),
            ])
            ->paginated(false);
    }
}
