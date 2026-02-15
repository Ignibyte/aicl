<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RlmLessonStatsOverview extends StatsOverviewWidget
{
    use PausesWhenHidden;

    protected static ?int $sort = 1;

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        $data = WidgetStatsCacheManager::getOrCompute(
            'rlm_lesson_stats',
            [WidgetStatsCacheManager::class, 'computeRlmLessonStats'],
        );

        $verifiedPercent = $data['total'] > 0 ? round(($data['verified'] / $data['total']) * 100, 1) : 0;

        return [
            Stat::make('Total Lessons', $data['total']),
            Stat::make('Verified', $data['verified'])
                ->description("{$verifiedPercent}% verified")
                ->color($verifiedPercent >= 80 ? 'success' : ($verifiedPercent >= 50 ? 'warning' : 'danger')),
            Stat::make('Unverified', $data['unverified'])
                ->color('warning'),
            Stat::make('Avg Confidence', $data['avg_confidence'] ? number_format((float) $data['avg_confidence'], 2) : 'N/A'),
        ];
    }
}
