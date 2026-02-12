<?php

namespace Aicl\Http\Requests;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRlmFailureRequest extends FormRequest
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
            'failure_code' => ['sometimes', 'required', 'string', 'max:255'],
            'pattern_id' => ['nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', Rule::enum(FailureCategory::class)],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'root_cause' => ['nullable', 'string'],
            'fix' => ['nullable', 'string'],
            'preventive_rule' => ['nullable', 'string'],
            'severity' => ['sometimes', 'required', 'string', Rule::enum(FailureSeverity::class)],
            'entity_context' => ['nullable', 'array'],
            'scaffolding_fixed' => ['boolean'],
            'first_seen_at' => ['nullable', 'date'],
            'last_seen_at' => ['nullable', 'date'],
            'aicl_version' => ['nullable', 'string', 'max:255'],
            'laravel_version' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['boolean'],
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
