<?php

namespace Aicl\Filament\Pages;

use Aicl\Filament\Resources\AiAgents\AiAgentResource;
use Aicl\Filament\Resources\AiConversations\AiConversationResource;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Tools extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Tools';

    protected static ?string $title = 'Tools';

    protected static ?string $slug = 'tools';

    protected string $view = 'aicl::filament.pages.tools';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Get the cards displayed on the Tools dashboard.
     *
     * @return array<array{title: string, description: string, icon: string, url: string}>
     */
    public function getCards(): array
    {
        return [
            [
                'title' => 'AI Agents',
                'description' => 'Configure AI agents, models, tools, and role-based access',
                'icon' => 'heroicon-o-cog-6-tooth',
                'url' => AiAgentResource::getUrl(),
            ],
            [
                'title' => 'AI Conversations',
                'description' => 'View and manage AI conversation history',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'url' => AiConversationResource::getUrl(),
            ],
            [
                'title' => 'Architecture Docs',
                'description' => 'Browse system architecture documentation and decision records',
                'icon' => 'heroicon-o-document-text',
                'url' => DocumentBrowser::getUrl(),
            ],
        ];
    }
}
