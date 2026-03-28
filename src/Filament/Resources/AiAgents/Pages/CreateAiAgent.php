<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiAgents\Pages;

use Aicl\Filament\Resources\AiAgents\AiAgentResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * CreateAiAgent.
 */
class CreateAiAgent extends CreateRecord
{
    protected static string $resource = AiAgentResource::class;
}
