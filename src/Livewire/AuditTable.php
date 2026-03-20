<?php

declare(strict_types=1);

namespace Aicl\Livewire;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/** Livewire table widget displaying the activity log audit trail. */
class AuditTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'aicl::livewire.audit-table';

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query())
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (Activity $record) => $record->created_at?->format('Y-m-d H:i:s') ?? ''),
                TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('causer', function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Entity Type')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A'),
                TextColumn::make('subject_id')
                    ->label('Entity ID'),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                SelectFilter::make('subject_type')
                    ->label('Entity Type')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->whereNotNull('subject_type')
                        ->pluck('subject_type')
                        ->mapWithKeys(fn (string $type) => [$type => class_basename($type)])
                        ->toArray()),
                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', Activity::query()
                            ->distinct()
                            ->whereNotNull('causer_id')
                            ->where('causer_type', User::class)
                            ->pluck('causer_id'))
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable(),
            ])
            ->emptyStateHeading('No audit records')
            ->emptyStateDescription('Activity will appear here as entities are created, updated, and deleted.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->paginated([10, 25, 50, 100]);
    }
}
