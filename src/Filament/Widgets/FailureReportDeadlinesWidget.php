<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\FailureReport;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class FailureReportDeadlinesWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Table will refresh on next poll
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Unresolved Reports')
            ->query(FailureReport::query()->unresolved()->where('is_active', true)->orderBy('reported_at', 'desc')->limit(5))
            ->columns([
                TextColumn::make('entity_name')->weight('bold'),
                TextColumn::make('failure.failure_code')->label('Failure Code'),
                TextColumn::make('phase')->badge(),
                TextColumn::make('agent')->badge()->color('gray'),
                IconColumn::make('resolved')->boolean(),
                TextColumn::make('reported_at')->dateTime()->label('Reported'),
            ])
            ->paginated(false);
    }
}
