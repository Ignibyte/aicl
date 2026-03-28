<?php

declare(strict_types=1);

namespace Aicl\Http\Requests;

use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreAiAgentRequest.
 */
class StoreAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiAgent::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:ai_agents,slug'],
            'description' => ['nullable', 'string'],
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'model' => ['required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string'],
            'max_tokens' => ['integer', 'min:1', 'max:1000000'],
            'temperature' => ['numeric', 'between:0,2.00'],
            'context_window' => ['integer', 'min:1'],
            'context_messages' => ['integer', 'between:1,100'],
            'is_active' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['integer', 'min:0'],
            'suggested_prompts' => ['nullable', 'array', 'max:10'],
            'suggested_prompts.*' => ['string', 'max:200'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:100'],
            'visible_to_roles' => ['nullable', 'array'],
            'visible_to_roles.*' => ['string'],
            'max_requests_per_minute' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
