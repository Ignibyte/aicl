<?php

namespace Aicl\AI;

use Illuminate\Foundation\Http\FormRequest;

class AiAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxLength = config('aicl.ai.max_prompt_length', 2000);

        return [
            'prompt' => ['required', 'string', "max:{$maxLength}"],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'string', 'max:255', 'required_with:entity_type'],
            'system_prompt' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'A prompt is required.',
            'prompt.max' => 'The prompt must not exceed :max characters.',
            'entity_id.required_with' => 'An entity ID is required when entity type is provided.',
        ];
    }
}
