<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\GenerationTrace;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class RecentGenerationTracesWidget extends TableWidget
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
            ->heading('Recent Generation Traces')
            ->query(GenerationTrace::query()->latest()->limit(10))
            ->columns([
                TextColumn::make('entity_name')
                    ->weight('bold'),
                TextColumn::make('structural_score')
                    ->label('Structural')
                    ->numeric(1)
                    ->suffix('%')
                    ->placeholder('—'),
                TextColumn::make('semantic_score')
                    ->label('Semantic')
                    ->numeric(1)
                    ->suffix('%')
                    ->placeholder('—'),
                TextColumn::make('fix_iterations')
                    ->label('Fixes'),
                IconColumn::make('is_processed')
                    ->label('Processed')
                    ->boolean(),
                TextColumn::make('aicl_version')
                    ->label('AICL')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->since(),
            ])
            ->paginated(false);
    }
}
