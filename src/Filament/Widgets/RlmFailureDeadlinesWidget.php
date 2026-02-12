<?php

namespace Aicl\Filament\Widgets;

use Aicl\Enums\FailureSeverity;
use Aicl\Models\RlmFailure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class RlmFailureDeadlinesWidget extends TableWidget
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
            ->heading('Recently Reported Failures')
            ->query(
                RlmFailure::query()
                    ->where('is_active', true)
                    ->with('owner')
                    ->orderBy('last_seen_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('failure_code')
                    ->weight('bold'),
                TextColumn::make('title')
                    ->limit(40),
                TextColumn::make('severity')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof FailureSeverity ? $state->label() : $state)
                    ->color(fn ($state) => match (true) {
                        $state instanceof FailureSeverity => $state->color(),
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('report_count')
                    ->numeric(),
                TextColumn::make('owner.name')
                    ->label('Owner'),
                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->label('Last Seen'),
            ])
            ->paginated(false);
    }
}
