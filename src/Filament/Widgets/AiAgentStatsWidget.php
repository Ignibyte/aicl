<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\States\AiAgent\Active;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiAgentStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return (bool) config('aicl.ai.assistant.enabled', false);
    }

    protected function getStats(): array
    {
        $activeCount = AiAgent::query()
            ->whereState('state', Active::class)
            ->where('is_active', true)
            ->count();

        $totalAgents = AiAgent::query()->count();
        $draftCount = $totalAgents - $activeCount;

        return [
            Stat::make('Active Agents', $activeCount)
                ->description($draftCount > 0 ? "{$draftCount} draft/archived" : 'All agents active')
                ->descriptionIcon($activeCount > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->color($activeCount > 0 ? 'success' : 'warning'),

            Stat::make('Total Conversations', number_format(AiConversation::query()->count()))
                ->description('Across all users')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('Total Tokens Used', number_format(AiConversation::query()->sum('token_count')))
                ->description('All conversations')
                ->descriptionIcon('heroicon-o-calculator')
                ->color('info'),
        ];
    }
}
