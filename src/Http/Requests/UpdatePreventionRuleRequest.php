<?php

namespace Aicl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreventionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('record'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rlm_failure_id' => ['nullable', 'exists:rlm_failures,id'],
            'trigger_context' => ['nullable', 'array'],
            'rule_text' => ['sometimes', 'required', 'string'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'priority' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'applied_count' => ['sometimes', 'integer', 'min:0'],
            'last_applied_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
