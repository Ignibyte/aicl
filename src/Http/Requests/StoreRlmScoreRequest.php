<?php

namespace Aicl\Http\Requests;

use Aicl\Enums\ScoreType;
use Aicl\Models\RlmScore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRlmScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RlmScore::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity_name' => ['required', 'string', 'max:255'],
            'score_type' => ['required', 'string', Rule::enum(ScoreType::class)],
            'passed' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:0'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'errors' => ['integer', 'min:0'],
            'warnings' => ['integer', 'min:0'],
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
