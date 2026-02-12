<?php

namespace Aicl\Http\Requests;

use Aicl\Models\PreventionRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePreventionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PreventionRule::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rlm_failure_id' => ['nullable', 'exists:rlm_failures,id'],
            'trigger_context' => ['nullable', 'array'],
            'rule_text' => ['required', 'string'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'priority' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'applied_count' => ['integer', 'min:0'],
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
