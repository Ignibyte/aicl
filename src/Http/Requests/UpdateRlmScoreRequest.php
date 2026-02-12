<?php

namespace Aicl\Http\Requests;

use Aicl\Enums\ScoreType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRlmScoreRequest extends FormRequest
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
            'entity_name' => ['sometimes', 'string', 'max:255'],
            'score_type' => ['sometimes', 'string', Rule::enum(ScoreType::class)],
            'passed' => ['sometimes', 'integer', 'min:0'],
            'total' => ['sometimes', 'integer', 'min:0'],
            'percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'errors' => ['sometimes', 'integer', 'min:0'],
            'warnings' => ['sometimes', 'integer', 'min:0'],
            'details' => ['nullable', 'array'],
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
