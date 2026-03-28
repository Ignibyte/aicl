<?php

declare(strict_types=1);

namespace Aicl\Http\Requests;

use Aicl\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateAiAgentRequest.
 */
class UpdateAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'provider' => ['sometimes', 'required', Rule::enum(AiProvider::class)],
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string'],
            'max_tokens' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'temperature' => ['sometimes', 'numeric', 'between:0,2.00'],
            'context_window' => ['sometimes', 'integer', 'min:1'],
            'context_messages' => ['sometimes', 'integer', 'between:1,100'],
            'is_active' => ['sometimes', 'boolean'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
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
