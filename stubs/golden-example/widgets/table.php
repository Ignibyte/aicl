<?php

// PATTERN: TableWidget for displaying entity data in a compact table.
// PATTERN: Uses query() to scope the data (e.g., upcoming deadlines).
// PATTERN: paginated(false) for small fixed-size tables.

namespace Aicl\Filament\Widgets;

use Aicl\States\Active;
use App\Models\Project;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class UpcomingDeadlinesWidget extends TableWidget
{
    protected static ?int $sort = 3;

    // PATTERN: Full-width widget.
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    #[On('entity-changed')]
    public function onEntityChanged(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading('Upcoming Deadlines')
            ->query(
                // PATTERN: Scoped query for specific data slice.
                Project::query()
                    ->where('status', Active::getMorphClass())
                    ->whereNotNull('end_date')
                    ->where('end_date', '>=', now())
                    ->orderBy('end_date')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('name')
                    ->weight('bold'),
                TextColumn::make('end_date')
                    ->label('Deadline')
                    ->date()
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('owner.name')
                    ->label('Owner'),
            ])
            // PATTERN: No pagination for small fixed-size widget tables.
            ->paginated(false);
    }
}
