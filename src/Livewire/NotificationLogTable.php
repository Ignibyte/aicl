<?php

declare(strict_types=1);

namespace Aicl\Livewire;

use Aicl\Models\NotificationLog;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Livewire table widget displaying the notification dispatch log. */
class NotificationLogTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'aicl::livewire.notification-log-table';

    public function table(Table $table): Table
    {
        return $table
            ->query(NotificationLog::query())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (NotificationLog $record) => $record->created_at?->format('Y-m-d H:i:s') ?? ''),
                TextColumn::make('notifiable.name')
                    ->label('Recipient')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHasMorph('notifiable', [User::class], function (Builder $q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('type_label')
                    ->label('Type'),
                TextColumn::make('data.title')
                    ->label('Title')
                    ->limit(50)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('data->title', 'like', "%{$search}%");
                    }),
                TextColumn::make('channels')
                    ->label('Channels')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(function ($state): string {
                        if (is_array($state)) {
                            return implode(',', $state);
                        }

                        return (string) $state;
                    }),
                TextColumn::make('channel_status')
                    ->label('Status')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state)) {
                            return (string) $state;
                        }

                        return collect($state)
                            ->map(fn (string $status, string $channel) => "{$channel}: {$status}")
                            ->implode(', ');
                    }),
                TextColumn::make('sender.name')
                    ->label('Triggered By')
                    ->default('System'),
                TextColumn::make('read_at')
                    ->label('Read')
                    ->since()
                    ->placeholder('Unread')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Notification Type')
                    ->options(fn (): array => NotificationLog::query()
                        ->distinct()
                        ->pluck('type')
                        ->mapWithKeys(fn (string $type) => [
                            $type => str(class_basename($type))
                                ->replaceLast('Notification', '')
                                ->headline()
                                ->toString(),
                        ])
                        ->toArray())
                    ->searchable(),
                SelectFilter::make('notifiable_id')
                    ->label('Recipient')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', NotificationLog::query()
                            ->distinct()
                            ->where('notifiable_type', User::class)
                            ->pluck('notifiable_id'))
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable(),
                SelectFilter::make('channel')
                    ->label('Channel')
                    ->options([
                        'database' => 'Database',
                        'mail' => 'Mail',
                        'broadcast' => 'Broadcast',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->where('channels', 'LIKE', "%\"{$data['value']}\"%");
                    }),
                SelectFilter::make('status')
                    ->label('Delivery Status')
                    ->options([
                        'sent' => 'All Sent',
                        'failed' => 'Has Failures',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'sent' => $query->where('channel_status', 'NOT LIKE', '%"failed"%'),
                            'failed' => $query->where('channel_status', 'LIKE', '%"failed"%'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('read_status')
                    ->label('Read Status')
                    ->options([
                        'unread' => 'Unread',
                        'read' => 'Read',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'unread' => $query->whereNull('read_at'),
                            'read' => $query->whereNotNull('read_at'),
                            default => $query,
                        };
                    }),
            ])
            ->emptyStateHeading('No notification logs')
            ->emptyStateDescription('Notification logs will appear here as notifications are sent through the system.')
            ->emptyStateIcon('heroicon-o-bell-slash')
            ->paginated([10, 25, 50, 100]);
    }
}
