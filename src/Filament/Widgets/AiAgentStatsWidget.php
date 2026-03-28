<?php

declare(strict_types=1);

namespace Aicl\Filament\Widgets;

use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\States\AiAgent\Active;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class AiAgentStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    /** Cache TTL for AI agent stats (seconds). */
    private const CACHE_TTL = 60;

    public static function canView(): bool
    {
        return (bool) config('aicl.ai.assistant.enabled', false);
    }

    protected function getStats(): array
    {
        $cached = Cache::remember('aicl:widget:ai-agent-stats', self::CACHE_TTL, function (): array {
            $activeCount = AiAgent::query()
                ->whereState('state', Active::class)
                ->where('is_active', true)
                ->count();

            $totalAgents = AiAgent::query()->count();

            return [
                'active_count' => $activeCount,
                'draft_count' => $totalAgents - $activeCount,
                'conversation_count' => AiConversation::query()->count(),
                'total_tokens' => (int) AiConversation::query()->sum('token_count'),
            ];
        });

        return [
            Stat::make('Active Agents', $cached['active_count'])
                ->description($cached['draft_count'] > 0 ? "{$cached['draft_count']} draft/archived" : 'All agents active')
                ->descriptionIcon($cached['active_count'] > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->color($cached['active_count'] > 0 ? 'success' : 'warning'),

            Stat::make('Total Conversations', number_format($cached['conversation_count']))
                ->description('Across all users')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('Total Tokens Used', number_format($cached['total_tokens']))
                ->description('All conversations')
                ->descriptionIcon('heroicon-o-calculator')
                ->color('info'),
        ];
    }
}
