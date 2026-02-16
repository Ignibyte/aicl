<?php

namespace Aicl\Filament\Pages;

use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class QueueDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Queue Dashboard';

    protected static ?string $title = 'Queue Dashboard';

    protected static ?string $slug = 'queue-dashboard';

    protected string $view = 'aicl::filament.pages.queue-dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            QueueStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RecentFailedJobsWidget::class,
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }
}
