<?php

namespace Aicl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRlmPatternRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'target' => ['sometimes', 'required', 'string', 'max:255'],
            'check_regex' => ['sometimes', 'required', 'string'],
            'severity' => ['sometimes', 'required', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'applies_when' => ['nullable', 'array'],
            'source' => ['sometimes', 'required', 'string', 'max:255'],
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
