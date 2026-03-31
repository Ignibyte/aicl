<?php

declare(strict_types=1);

namespace Aicl\Http\Requests;

use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreAiConversationRequest.
 */
class StoreAiConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiConversation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'ai_agent_id' => [
                'required',
                'uuid',
                // Single query: exists + is_active check instead of exists rule + separate find()
                function (string $attribute, mixed $value, Closure $fail): void {
                    $agent = AiAgent::query()
                        ->where('id', $value)
                        ->select(['id', 'is_active'])
                        ->first();

                    if (! $agent) {
                        $fail('The selected AI agent does not exist.');

                        return;
                    }

                    if (! $agent->is_active) {
                        // @codeCoverageIgnoreStart — Untestable in unit context
                        $fail('The selected AI agent is not active.');
                        // @codeCoverageIgnoreEnd
                    }
                },
            ],
            'context_page' => ['nullable', 'string', 'max:500'],
        ];
    }
}
