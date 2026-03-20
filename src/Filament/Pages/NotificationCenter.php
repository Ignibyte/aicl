<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages;

use Aicl\Swoole\Cache\NotificationBadgeCacheManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use UnitEnum;

/** Filament page providing a unified notification inbox with read/unread filtering and bulk actions. */
class NotificationCenter extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Notifications';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Notifications';

    protected static ?string $slug = 'notifications';

    protected string $view = 'aicl::filament.pages.notification-center';

    public string $filter = 'all';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('filter')
                    ->label('Show')
                    ->options([
                        'all' => 'All Notifications',
                        'unread' => 'Unread Only',
                        'read' => 'Read Only',
                    ])
                    ->default('all')
                    ->live(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->icon(fn ($state) => $state ? 'heroicon-o-envelope-open' : 'heroicon-o-envelope')
                    ->color(fn ($state) => $state ? 'gray' : 'primary')
                    ->size('sm'),
                TextColumn::make('data.title')
                    ->label('Title')
                    ->weight(fn (DatabaseNotification $record) => $record->read_at ? 'normal' : 'bold')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('data->title', 'like', "%{$search}%");
                    }),
                TextColumn::make('data.body')
                    ->label('Message')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('read_status')
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
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (DatabaseNotification $record): ?string => $record->data['action_url'] ?? null)
                    ->openUrlInNewTab()
                    ->visible(fn (DatabaseNotification $record): bool => isset($record->data['action_url']))
                    ->after(function (DatabaseNotification $record): void {
                        $record->markAsRead();
                    }),
                Action::make('mark_read')
                    ->label(fn (DatabaseNotification $record) => $record->read_at ? 'Mark Unread' : 'Mark Read')
                    ->icon(fn (DatabaseNotification $record) => $record->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                    ->action(function (DatabaseNotification $record): void {
                        if ($record->read_at) {
                            $record->update(['read_at' => null]);
                        } else {
                            $record->markAsRead();
                        }
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (DatabaseNotification $record) => $record->delete()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_read')
                        ->label('Mark as Read')
                        ->icon('heroicon-o-envelope-open')
                        ->action(function (Collection $records): void {
                            $records->each->markAsRead();

                            Notification::make()
                                ->success()
                                ->title('Notifications marked as read')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('mark_unread')
                        ->label('Mark as Unread')
                        ->icon('heroicon-o-envelope')
                        ->action(function (Collection $records): void {
                            $records->each(fn ($n) => $n->update(['read_at' => null]));

                            Notification::make()
                                ->success()
                                ->title('Notifications marked as unread')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('delete')
                        ->label('Delete Selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each->delete();

                            Notification::make()
                                ->success()
                                ->title('Notifications deleted')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->headerActions([
                Action::make('mark_all_read')
                    ->label('Mark All Read')
                    ->icon('heroicon-o-check')
                    ->action(function (): void {
                        auth()->user()->unreadNotifications->markAsRead();

                        Notification::make()
                            ->success()
                            ->title('All notifications marked as read')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No notifications')
            ->emptyStateDescription('You\'re all caught up!')
            ->emptyStateIcon('heroicon-o-bell-slash')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    protected function getTableQuery(): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', get_class(auth()->user()))
            ->where('notifiable_id', auth()->id());
    }

    public static function getNavigationBadge(): ?string
    {
        return NotificationBadgeCacheManager::getBadge((int) auth()->id());
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }
}
