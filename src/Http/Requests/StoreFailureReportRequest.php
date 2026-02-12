<?php

namespace Aicl\Http\Requests;

use Aicl\Enums\ResolutionMethod;
use Aicl\Models\FailureReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFailureReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FailureReport::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rlm_failure_id' => ['required', 'exists:rlm_failures,id'],
            'project_hash' => ['required', 'string', 'max:255'],
            'entity_name' => ['required', 'string', 'max:255'],
            'scaffolder_args' => ['nullable', 'array'],
            'phase' => ['nullable', 'string', 'max:255'],
            'agent' => ['nullable', 'string', 'max:255'],
            'resolved' => ['boolean'],
            'resolution_notes' => ['nullable', 'string'],
            'resolution_method' => ['nullable', Rule::enum(ResolutionMethod::class)],
            'time_to_resolve' => ['nullable', 'integer', 'min:0'],
            'reported_at' => ['required', 'date'],
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
