<?php

namespace Aicl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'ai_agent_id' => ['required', 'uuid', 'exists:ai_agents,id'],
            'context_page' => ['nullable', 'string', 'max:500'],
        ];
    }
}
