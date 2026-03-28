<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiAgents\Schemas;

use Aicl\States\AiAgent\AiAgentState;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @codeCoverageIgnore Filament Livewire rendering
 */
class AiAgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('slug'),
                    TextEntry::make('description')
                        ->columnSpanFull()
                        ->default('—'),
                    IconEntry::make('is_active')
                        ->label('Visible in Widget')
                        ->boolean(),
                ]),

            Section::make('Provider & Model')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('provider')
                        ->badge(),
                    TextEntry::make('model'),
                    TextEntry::make('temperature'),
                    TextEntry::make('max_tokens'),
                ]),

            Section::make('Context & Behavior')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('system_prompt')
                        ->columnSpanFull()
                        ->default('—'),
                    TextEntry::make('context_window'),
                    TextEntry::make('context_messages'),
                ]),

            Section::make('Prompts & Capabilities')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('suggested_prompts')
                        ->badge()
                        ->columnSpanFull()
                        ->default('—'),
                    TextEntry::make('capabilities')
                        ->badge()
                        ->columnSpanFull()
                        ->default('—'),
                ]),

            Section::make('Appearance & Access')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('icon')
                        ->default('—'),
                    TextEntry::make('color')
                        ->default('—'),
                    TextEntry::make('sort_order'),
                    TextEntry::make('visible_to_roles')
                        ->badge()
                        ->default('All roles'),
                    TextEntry::make('max_requests_per_minute')
                        ->default('Unlimited'),
                    TextEntry::make('state')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof AiAgentState ? $state->label() : (string) $state)
                        ->color(fn ($state): string => $state instanceof AiAgentState ? $state->color() : 'gray'),
                ]),

            Section::make('Timestamps')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')
                        ->dateTime(),
                    TextEntry::make('updated_at')
                        ->dateTime(),
                ]),
        ]);
    }
}
