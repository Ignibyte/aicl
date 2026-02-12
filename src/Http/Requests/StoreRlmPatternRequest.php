<?php

namespace Aicl\Http\Requests;

use Aicl\Models\RlmPattern;
use Illuminate\Foundation\Http\FormRequest;

class StoreRlmPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RlmPattern::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:rlm_patterns,name'],
            'description' => ['required', 'string'],
            'target' => ['required', 'string', 'max:255'],
            'check_regex' => ['required', 'string'],
            'severity' => ['required', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'category' => ['required', 'string', 'max:255'],
            'applies_when' => ['nullable', 'array'],
            'source' => ['required', 'string', 'max:255'],
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
