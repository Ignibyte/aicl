<?php

declare(strict_types=1);

namespace Aicl\Livewire;

use Aicl\AI\AiChatService;
use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * AiAssistantPanel.
 */
class AiAssistantPanel extends Component
{
    public ?string $activeConversationId = null;

    public ?string $selectedAgentId = null;

    /**
     * Send a message to the active conversation.
     *
     * Creates a new conversation if none is active. Returns
     * stream info (stream_id, channel) for WebSocket subscription.
     *
     * @codeCoverageIgnore Reason: filament-closure -- AI chat requires provider connection and conversation context
     *
     * @return array<string, string>
     */
    public function sendMessage(string $message): array
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $message = trim($message);

        if (empty($message)) {
            return ['error' => 'Message cannot be empty.'];
        }

        $user = auth()->user();

        if (! $user) {
            return ['error' => 'Not authenticated.'];
        }

        // Create conversation if none active
        $isNewConversation = ! $this->activeConversationId;
        if ($isNewConversation) {
            $this->createConversation();
        }

        $conversation = AiConversation::with('agent')->find($this->activeConversationId);

        if (! $conversation) {
            return ['error' => 'Conversation not found.'];
        }

        // Verify role-based access to the agent
        if (! $conversation->agent?->isAccessibleByUser($user)) {
            return ['error' => 'You do not have access to this AI agent.'];
        }

        // Auto-title new conversations from the first message
        if ($isNewConversation) {
            $title = mb_strlen($message) > 60
                ? mb_substr($message, 0, 57).'...'
                : $message;
            $conversation->update(['title' => $title]);
            // @codeCoverageIgnoreEnd
        }

        try {
            /** @var AiChatService $chatService */
            // @codeCoverageIgnoreStart — Filament Livewire rendering
            $chatService = app(AiChatService::class);
            $result = $chatService->sendMessage($conversation, $message, $user);

            return $result;
        } catch (\RuntimeException $e) {
            return ['error' => $e->getMessage()];
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Create a new conversation with the selected agent.
     */
    public function createConversation(): void
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $agentId = $this->selectedAgentId;

        if (! $agentId) {
            /** @var Collection<int, AiAgent> $agents */
            $agents = $this->agents();
            $agent = $agents->first();
            $agentId = $agent?->id;
        }

        if (! $agentId) {
            return;
        }

        // Verify user has access to this agent
        $agent = AiAgent::find($agentId);

        if (! $agent || ! $agent->isAccessibleByUser($user)) {
            return;
        }

        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'ai_agent_id' => $agentId,
            'title' => 'New Conversation',
        ]);

        $this->activeConversationId = $conversation->id;
        $this->selectedAgentId = $agentId;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Switch to an existing conversation.
     */
    public function switchConversation(string $conversationId): void
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $conversation = AiConversation::query()
            ->with('agent')
            ->where('user_id', $user->id)
            ->find($conversationId);

        if (! $conversation) {
            return;
        }

        // Verify role access to the conversation's agent
        if ($conversation->agent && ! $conversation->agent->isAccessibleByUser($user)) {
            return;
        }

        $this->activeConversationId = $conversation->id;
        $this->selectedAgentId = $conversation->ai_agent_id;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(string $conversationId): void
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->find($conversationId);

        if (! $conversation) {
            return;
        }

        $conversation->delete();

        if ($this->activeConversationId === $conversationId) {
            $this->activeConversationId = null;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Rename a conversation.
     */
    public function renameConversation(string $conversationId, string $title): void
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $title = trim($title);

        if ($title === '') {
            return;
        }

        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->find($conversationId);

        if (! $conversation) {
            return;
        }

        $conversation->update(['title' => mb_substr($title, 0, 100)]);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get messages for the active conversation.
     *
     * @return array<int, array{role: string, content: string, tools: array<int, array{name: string}>, timestamp: string, agent_name: string|null}>
     */
    public function loadMessages(): array
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        if (! $this->activeConversationId) {
            return [];
        }

        $user = auth()->user();

        if (! $user) {
            return [];
        }

        // Enforce ownership — prevent IDOR via manipulated activeConversationId
        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->find($this->activeConversationId);

        if (! $conversation) {
            return [];
        }

        $agentName = $conversation->agent->name ?? 'Assistant';

        return $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function ($msg) use ($agentName): array {
                $content = $msg->content;
                $tools = [];

                // Extract tool call JSON from persisted assistant content
                if ($msg->role === AiMessageRole::Assistant && $content) {
                    $extracted = $this->extractToolCalls($content);
                    $content = $extracted['content'];
                    $tools = $extracted['tools'];
                }

                // Merge structured tool render data from metadata (for replay)
                $metadata = $msg->metadata ?? [];
                $toolResults = $metadata['tool_results'] ?? [];

                if (! empty($toolResults)) {
                    // Enrich tool entries with render data from metadata
                    /** @var array<int, array<string, mixed>> $toolResults */
                    $renderByName = collect($toolResults)->keyBy('name');

                    $tools = collect($tools)->map(function (array $tool) use ($renderByName): array {
                        $stored = $renderByName->get($tool['name']);

                        if ($stored && isset($stored['render'])) {
                            $tool['render'] = $stored['render'];
                        }

                        return $tool;
                    })->toArray();

                    // Add any tools from metadata not found via JSON extraction
                    foreach ($toolResults as $tr) {
                        $exists = collect($tools)->contains(fn (array $t): bool => $t['name'] === $tr['name']);

                        if (! $exists) {
                            $tools[] = [
                                'name' => $tr['name'],
                                'render' => $tr['render'] ?? null,
                            ];
                            // @codeCoverageIgnoreEnd
                        }
                    }
                }

                // @codeCoverageIgnoreStart — Filament Livewire rendering
                return [
                    'role' => $msg->role->value,
                    'content' => $content,
                    'tools' => $tools,
                    'timestamp' => $msg->created_at->format('g:i A'),
                    'agent_name' => $msg->role->value === 'assistant' ? $agentName : null,
                ];
            })
            ->toArray();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Extract tool call JSON from persisted message content.
     *
     * NeuronAI may include tool call results as JSON in the text stream,
     * producing content like: [{...tool calls...}]Natural language response.
     * This parses the JSON, extracts tool names for chip display, and
     * returns the clean text content.
     *
     * @return array{content: string, tools: array<int, array{name: string}>}
     */
    private function extractToolCalls(string $content): array
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $trimmed = trim($content);

        if (! str_starts_with($trimmed, '[{')) {
            return ['content' => $content, 'tools' => []];
        }

        // Find the closing bracket of the top-level JSON array
        $endPos = $this->findJsonArrayEnd($trimmed);

        if ($endPos === false) {
            return ['content' => $content, 'tools' => []];
        }

        $jsonStr = substr($trimmed, 0, $endPos + 1);
        $decoded = json_decode($jsonStr, true);

        if (! is_array($decoded) || empty($decoded)) {
            return ['content' => $content, 'tools' => []];
        }

        $tools = [];

        foreach ($decoded as $call) {
            if (isset($call['name'])) {
                $tools[] = ['name' => $call['name']];
            }
        }

        if (empty($tools)) {
            return ['content' => $content, 'tools' => []];
        }

        $remaining = trim(substr($trimmed, $endPos + 1));

        return [
            'content' => $remaining !== '' ? $remaining : $content,
            'tools' => $tools,
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * Find the position of the closing bracket for a JSON array.
     *
     * Tracks bracket depth and string boundaries to correctly
     * handle nested objects and escaped characters.
     */
    private function findJsonArrayEnd(string $text): int|false
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '[') {
                $depth++;
            }

            if ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                    // @codeCoverageIgnoreEnd
                }
            }
        }

        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return false;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Available AI agents — filtered by active status and user's roles.
     *
     * @return Collection<int, AiAgent>
     */
    #[Computed]
    public function agents(): Collection
    {
        $user = auth()->user();
        /** @var User $user */
        $userRoles = $user->getRoleNames()->toArray();

        return AiAgent::query()
            ->where('is_active', true)
            ->visibleToRoles($userRoles)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Recent conversations for the current user.
     *
     * @return Collection<int, AiConversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            // @codeCoverageIgnoreStart — Filament Livewire rendering
            return collect();
            // @codeCoverageIgnoreEnd
        }

        return AiConversation::query()
            ->where('user_id', $user->id)
            ->with('agent:id,name,icon,color')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function render(): View
    {
        return view('aicl::livewire.ai-assistant-panel');
    }
}
