<?php

namespace Aicl\Http\Requests;

use Aicl\Models\RlmFailure;
use Illuminate\Foundation\Http\FormRequest;

class UpsertRlmFailureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RlmFailure::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'failure_code' => ['required', 'string', 'max:255'],
            'pattern_id' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'root_cause' => ['nullable', 'string'],
            'fix' => ['nullable', 'string'],
            'preventive_rule' => ['nullable', 'string'],
            'severity' => ['required', 'string'],
            'entity_context' => ['nullable', 'array'],
            'scaffolding_fixed' => ['boolean'],
            'aicl_version' => ['nullable', 'string', 'max:255'],
            'laravel_version' => ['nullable', 'string', 'max:255'],
            'project_hash' => ['nullable', 'string', 'max:64'],
        ];
    }
}
