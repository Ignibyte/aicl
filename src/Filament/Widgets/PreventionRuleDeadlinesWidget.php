<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\PreventionRule;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class PreventionRuleDeadlinesWidget extends TableWidget
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
            ->heading('Most Applied Prevention Rules')
            ->query(PreventionRule::query()->where('is_active', true)->orderBy('applied_count', 'desc')->limit(5))
            ->columns([
                TextColumn::make('rule_text')
                    ->label('Rule')
                    ->limit(60)
                    ->weight('bold'),
                TextColumn::make('failure.failure_code')
                    ->label('Failure')
                    ->badge()
                    ->color('danger')
                    ->placeholder('Standalone'),
                TextColumn::make('confidence')
                    ->numeric(2),
                TextColumn::make('applied_count')
                    ->label('Applied')
                    ->numeric(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->paginated(false);
    }
}
