<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\RlmLesson;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RlmLessonStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        $total = RlmLesson::query()->count();
        $verified = RlmLesson::query()->verified()->count();
        $verifiedPercent = $total > 0 ? round(($verified / $total) * 100, 1) : 0;
        $avgConfidence = RlmLesson::query()->avg('confidence');

        return [
            Stat::make('Total Lessons', $total),
            Stat::make('Verified', $verified)
                ->description("{$verifiedPercent}% verified")
                ->color($verifiedPercent >= 80 ? 'success' : ($verifiedPercent >= 50 ? 'warning' : 'danger')),
            Stat::make('Unverified', RlmLesson::query()->unverified()->count())
                ->color('warning'),
            Stat::make('Avg Confidence', $avgConfidence ? number_format((float) $avgConfidence, 2) : 'N/A'),
        ];
    }
}
