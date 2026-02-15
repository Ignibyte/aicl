<?php

namespace Aicl\Filament\Pages;

use Aicl\Filament\Widgets\CategoryBreakdownChart;
use Aicl\Filament\Widgets\FailureTrendChart;
use Aicl\Filament\Widgets\ProjectHealthWidget;
use Aicl\Filament\Widgets\PromotionQueueWidget;
use Filament\Pages\Page;
use UnitEnum;

class RlmDashboard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'RLM Hub';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'RLM Dashboard';

    protected static ?string $title = 'RLM Dashboard';

    protected static ?string $slug = 'rlm-dashboard';

    protected string $view = 'aicl::filament.pages.rlm-dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            FailureTrendChart::class,
            CategoryBreakdownChart::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PromotionQueueWidget::class,
            ProjectHealthWidget::class,
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
