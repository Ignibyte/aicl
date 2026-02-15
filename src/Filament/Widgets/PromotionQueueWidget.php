<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Models\RlmFailure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class PromotionQueueWidget extends TableWidget
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
            ->heading('Promotion Queue')
            ->description('Failures eligible for promotion to base failures')
            ->query(
                RlmFailure::query()
                    ->promotable()
                    ->orderByDesc('report_count')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('failure_code')->badge()->weight('bold'),
                TextColumn::make('title')->limit(40),
                TextColumn::make('category')->badge(),
                TextColumn::make('report_count')->numeric()->label('Reports'),
                TextColumn::make('project_count')->numeric()->label('Projects'),
                TextColumn::make('resolution_rate')
                    ->numeric(decimalPlaces: 1)
                    ->suffix('%')
                    ->label('Resolution'),
            ])
            ->paginated(false);
    }
}
