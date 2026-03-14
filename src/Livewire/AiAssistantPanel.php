<?php

namespace Aicl\Livewire;

use Aicl\AI\AiChatService;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

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
     * @return array<string, string>
     */
    public function sendMessage(string $message): array
    {
        $message = trim($message);

        if (empty($message)) {
            return ['error' => 'Message cannot be empty.'];
        }

        $user = auth()->user();

        if (! $user) {
            return ['error' => 'Not authenticated.'];
        }

        // Create conversation if none active
        if (! $this->activeConversationId) {
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

        try {
            /** @var AiChatService $chatService */
            $chatService = app(AiChatService::class);
            $result = $chatService->sendMessage($conversation, $message, $user);

            return $result;
        } catch (\RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a new conversation with the selected agent.
     */
    public function createConversation(): void
    {
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
    }

    /**
     * Switch to an existing conversation.
     */
    public function switchConversation(string $conversationId): void
    {
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
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(string $conversationId): void
    {
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
        }
    }

    /**
     * Get messages for the active conversation.
     *
     * @return array<int, array{role: string, content: string, timestamp: string, agent_name: string|null}>
     */
    public function loadMessages(): array
    {
        if (! $this->activeConversationId) {
            return [];
        }

        $conversation = AiConversation::find($this->activeConversationId);

        if (! $conversation) {
            return [];
        }

        $agentName = $conversation->agent->name ?? 'Assistant';

        return $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg): array => [
                'role' => $msg->role->value,
                'content' => $msg->content,
                'timestamp' => $msg->created_at->format('g:i A'),
                'agent_name' => $msg->role->value === 'assistant' ? $agentName : null,
            ])
            ->toArray();
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
            return collect();
        }

        return AiConversation::query()
            ->where('user_id', $user->id)
            ->with('agent:id,name,icon,color')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('aicl::livewire.ai-assistant-panel');
    }
}
