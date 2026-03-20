<?php

namespace Aicl\Filament\Resources\AiConversations\Pages;

use Aicl\Filament\Resources\AiConversations\AiConversationResource;
use Aicl\Models\AiConversation;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAiConversations extends ListRecords
{
    protected static string $resource = AiConversationResource::class;

    /**
     * Eager load user and agent relationships to prevent N+1 queries
     * on the table columns that reference user.name and agent.name.
     *
     * @param  Builder<AiConversation>  $query
     * @return Builder<AiConversation>
     */
    protected function modifyQueryUsing(Builder $query): Builder
    {
        return $query->with(['user', 'agent']);
    }
}
