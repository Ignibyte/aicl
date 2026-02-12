<?php

namespace Aicl\Http\Requests;

use Aicl\Enums\ResolutionMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFailureReportRequest extends FormRequest
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
            'rlm_failure_id' => ['sometimes', 'required', 'exists:rlm_failures,id'],
            'project_hash' => ['sometimes', 'required', 'string', 'max:255'],
            'entity_name' => ['sometimes', 'required', 'string', 'max:255'],
            'scaffolder_args' => ['nullable', 'array'],
            'phase' => ['nullable', 'string', 'max:255'],
            'agent' => ['nullable', 'string', 'max:255'],
            'resolved' => ['boolean'],
            'resolution_notes' => ['nullable', 'string'],
            'resolution_method' => ['nullable', Rule::enum(ResolutionMethod::class)],
            'time_to_resolve' => ['nullable', 'integer', 'min:0'],
            'reported_at' => ['sometimes', 'required', 'date'],
            'resolved_at' => ['nullable', 'date', 'after_or_equal:reported_at'],
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
