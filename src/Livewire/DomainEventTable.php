<?php

declare(strict_types=1);

namespace Aicl\Livewire;

use Aicl\Events\Enums\ActorType;
use Aicl\Models\DomainEventRecord;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Livewire table widget displaying domain events with actor, type, and date filtering. */
class DomainEventTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'aicl::livewire.domain-event-table';

    public function table(Table $table): Table
    {
        return $table
            ->query(DomainEventRecord::query())
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (DomainEventRecord $record): string => $record->occurred_at->format('Y-m-d H:i:s')),
                TextColumn::make('actor_type')
                    ->label('Who')
                    ->badge()
                    ->formatStateUsing(function (?string $state, DomainEventRecord $record): string {
                        $label = ($state !== null ? ActorType::tryFrom($state)?->label() : null) ?? $state ?? 'Unknown';

                        if ($record->actor_id && $state === 'user') {
                            $user = User::find($record->actor_id);
                            if ($user) {
                                return $label.': '.$user->name;
                            }
                        }

                        return $label;
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'user' => 'info',
                        'system' => 'gray',
                        'agent' => 'warning',
                        'automation' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('event_type')
                    ->label('What')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('entity_type')
                    ->label('Where')
                    ->formatStateUsing(function (?string $state, DomainEventRecord $record): string {
                        if (! $state) {
                            return 'N/A';
                        }

                        $basename = class_basename($state);

                        return $record->entity_id
                            ? $basename.' #'.$record->entity_id
                            : $basename;
                    }),
                TextColumn::make('payload')
                    ->label('Details')
                    ->formatStateUsing(fn ($state): string => is_array($state) && ! empty($state)
                        ? (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        : '{}')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('actor_type')
                    ->label('Actor Type')
                    ->options(
                        collect(ActorType::cases())
                            ->mapWithKeys(fn (ActorType $t) => [$t->value => $t->label()])
                            ->toArray()
                    ),
                SelectFilter::make('actor_id')
                    ->label('User')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', DomainEventRecord::query()
                            ->distinct()
                            ->whereNotNull('actor_id')
                            ->where('actor_type', 'user')
                            ->pluck('actor_id'))
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable(),
                SelectFilter::make('entity_type')
                    ->label('Entity Type')
                    ->options(fn (): array => DomainEventRecord::query()
                        ->distinct()
                        ->whereNotNull('entity_type')
                        ->pluck('entity_type')
                        ->mapWithKeys(fn (string $type) => [$type => class_basename($type)])
                        ->toArray()),
                Filter::make('event_type_filter')
                    ->form([
                        TextInput::make('event_type')
                            ->label('Event Type')
                            ->placeholder('e.g., order.* or *.escalated'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<DomainEventRecord> $query */
                        return $query->when(
                            $data['event_type'] ?? null,
                            fn (Builder $q, string $type): Builder => $q->where('event_type', str_contains($type, '*') ? 'LIKE' : '=', str_replace('*', '%', $type)),
                        );
                    }),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date): Builder => $q->where('occurred_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date): Builder => $q->where('occurred_at', '<=', $date)
                            );
                    }),
            ])
            ->emptyStateHeading('No domain events')
            ->emptyStateDescription('Domain events will appear here as they are dispatched throughout the application.')
            ->emptyStateIcon('heroicon-o-bolt')
            ->paginated([10, 25, 50, 100]);
    }
}
