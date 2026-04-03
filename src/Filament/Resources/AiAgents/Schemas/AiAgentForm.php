<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\AiAgents\Schemas;

use Aicl\AI\AiToolRegistry;
use Aicl\Enums\AiProvider;
use Aicl\States\AiAgent\Active;
use Aicl\States\AiAgent\Archived;
use Aicl\States\AiAgent\Draft;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Filament form schema definition for the AiAgent resource.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AiAgentForm
{
    private static function getIdentitySection(): Section
    {
        return Section::make('Identity')
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Visible in AI Assistant widget')
                    ->inline(),
            ]);
    }

    private static function getProviderSection(): Section
    {
        return Section::make('Provider & Model')
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Select::make('provider')
                    ->options(AiProvider::class)
                    ->required()
                    ->reactive(),
                TextInput::make('model')
                    ->required()
                    ->maxLength(255)
                    ->placeholder(fn ($get): string => match ($get('provider')) { // @codeCoverageIgnore
                        'openai' => 'gpt-4o', // @codeCoverageIgnore
                        'anthropic' => 'claude-sonnet-4-20250514', // @codeCoverageIgnore
                        'ollama' => 'llama3.2', // @codeCoverageIgnore
                        default => 'model-name', // @codeCoverageIgnore
                    }),
                TextInput::make('temperature')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(2.00)
                    ->default(0.70)
                    ->helperText('0.0 = deterministic, 2.0 = creative'),
                TextInput::make('max_tokens')
                    ->numeric()
                    ->minValue(1)
                    ->default(4096),
            ]);
    }

    private static function getContextSection(): Section
    {
        return Section::make('Context & Behavior')
            ->columnSpanFull()
            ->schema([
                Textarea::make('system_prompt')
                    ->rows(6)
                    ->columnSpanFull()
                    ->placeholder('You are a helpful assistant...'),
                TextInput::make('context_window')
                    ->numeric()
                    ->default(128000)
                    ->helperText('Maximum context size in tokens'),
                TextInput::make('context_messages')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(20)
                    ->helperText('Recent messages to include in context'),
            ]);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function getToolsSection(): Section
    {
        return Section::make('Function Tools')
            ->description('Control which tools this agent can use. Tools allow the AI to perform actions like querying data, checking system status, and more.')
            ->columnSpanFull()
            ->schema([
                Toggle::make('capabilities.tools_enabled')
                    ->label('Enable function tools')
                    ->helperText('When disabled, this agent will only respond with text — no tool calls.')
                    ->inline()
                    ->reactive()
                    ->afterStateHydrated(function ($component, $state, $record): void { // @codeCoverageIgnore
                        if ($record) { // @codeCoverageIgnore
                            $capabilities = $record->capabilities ?? []; // @codeCoverageIgnore
                            $component->state($capabilities['tools_enabled'] ?? false); // @codeCoverageIgnore
                        }
                    }),
                CheckboxList::make('capabilities.allowed_tools')
                    ->label('Allowed Tools')
                    ->helperText('Select which tools this agent can use. Leave empty to allow all registered tools.')
                    ->options(fn (): array => static::getRegisteredToolOptions())
                    ->columns(2)
                    ->visible(fn ($get): bool => (bool) $get('capabilities.tools_enabled'))
                    ->afterStateHydrated(function ($component, $_state, $record): void { // @codeCoverageIgnore
                        if ($record) { // @codeCoverageIgnore
                            $capabilities = $record->capabilities ?? []; // @codeCoverageIgnore
                            $component->state($capabilities['allowed_tools'] ?? []); // @codeCoverageIgnore
                        }
                    }),
            ]);
    }

    private static function getPromptsSection(): Section
    {
        return Section::make('Suggested Prompts')
            ->columnSpanFull()
            ->schema([
                Repeater::make('suggested_prompts')
                    ->simple(
                        TextInput::make('prompt')
                            ->maxLength(200),
                    )
                    ->maxItems(10)
                    ->columnSpanFull()
                    ->defaultItems(0),
            ]);
    }

    /**
     * @codeCoverageIgnore Reason: filament-closure — Role::class options closure not invoked in unit tests
     */
    private static function getAccessSection(): Section
    {
        return Section::make('Access Control & Appearance')
            ->description('Control which roles can see and use this agent.')
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Select::make('visible_to_roles')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => class_exists(Role::class) // @codeCoverageIgnore
                        ? Role::query()->pluck('name', 'name')->toArray()
                        : [])
                    ->placeholder('All roles (no restriction)')
                    ->helperText('When empty, all users can access this agent. Select roles to restrict access.')
                    ->visible(fn (): bool => class_exists(Role::class))
                    ->columnSpanFull(),
                TextInput::make('icon')
                    ->placeholder('heroicon-o-cpu-chip'),
                ColorPicker::make('color'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                TextInput::make('max_requests_per_minute')
                    ->numeric()
                    ->placeholder('Unlimited'),
                Select::make('state')
                    ->options([
                        Draft::class => 'Draft',
                        Active::class => 'Active',
                        Archived::class => 'Archived',
                    ])
                    ->hidden(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    /**
     * Get registered AI tools as options for the checkbox list.
     *
     * @return array<string, string>
     */
    protected static function getRegisteredToolOptions(): array
    {
        try {
            $registry = app(AiToolRegistry::class);
            $tools = $registry->registered();

            $options = [];

            foreach ($tools as $fqcn) {
                // Use the short class name as label
                $shortName = class_basename($fqcn);
                // Convert CamelCase to readable: "QueryEntityTool" -> "Query Entity"
                $label = preg_replace('/Tool$/', '', $shortName) ?? $shortName;
                $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label) ?? $label;
                $options[$fqcn] = trim($label);
            }

            return $options;
            // @codeCoverageIgnoreStart — Filament Livewire rendering
        } catch (Throwable) {
            return [];
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @codeCoverageIgnore Reason: filament-closure -- Form schema with reactive closures untestable in PHPUnit
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::getIdentitySection(),
                static::getProviderSection(),
                static::getContextSection(),
                static::getToolsSection(),
                static::getPromptsSection(),
                static::getAccessSection(),
            ]);
        // @codeCoverageIgnoreEnd
    }
}
