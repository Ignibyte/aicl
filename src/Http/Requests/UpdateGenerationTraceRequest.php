<?php

namespace Aicl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGenerationTraceRequest extends FormRequest
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
            'entity_name' => ['sometimes', 'required', 'string', 'max:255'],
            'project_hash' => ['nullable', 'string', 'max:255'],
            'scaffolder_args' => ['sometimes', 'required', 'string'],
            'file_manifest' => ['nullable', 'array'],
            'file_manifest.*' => ['string'],
            'structural_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'semantic_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'test_results' => ['nullable', 'string'],
            'fixes_applied' => ['nullable', 'array'],
            'fixes_applied.*' => ['string'],
            'fix_iterations' => ['sometimes', 'integer', 'min:0'],
            'pipeline_duration' => ['nullable', 'integer', 'min:0'],
            'agent_versions' => ['nullable', 'array'],
            'is_processed' => ['boolean'],
            'aicl_version' => ['nullable', 'string', 'max:255'],
            'laravel_version' => ['nullable', 'string', 'max:255'],
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
