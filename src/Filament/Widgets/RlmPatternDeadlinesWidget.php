<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\RlmPattern;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class RlmPatternDeadlinesWidget extends TableWidget
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
            ->heading('Recently Evaluated Patterns')
            ->query(
                RlmPattern::query()
                    ->where('is_active', true)
                    ->whereNotNull('last_evaluated_at')
                    ->with('owner')
                    ->orderBy('last_evaluated_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->weight('bold'),
                TextColumn::make('target')
                    ->badge(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('pass_count')
                    ->numeric()
                    ->label('Pass'),
                TextColumn::make('fail_count')
                    ->numeric()
                    ->label('Fail'),
                TextColumn::make('owner.name')
                    ->label('Owner'),
                TextColumn::make('last_evaluated_at')
                    ->dateTime()
                    ->label('Last Evaluated'),
            ])
            ->paginated(false);
    }
}
