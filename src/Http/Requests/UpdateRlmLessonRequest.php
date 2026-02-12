<?php

namespace Aicl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRlmLessonRequest extends FormRequest
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
            'topic' => ['sometimes', 'required', 'string', 'max:255'],
            'subtopic' => ['nullable', 'string', 'max:255'],
            'summary' => ['sometimes', 'required', 'string', 'max:255'],
            'detail' => ['sometimes', 'required', 'string'],
            'tags' => ['nullable', 'string', 'max:255'],
            'context_tags' => ['nullable', 'array'],
            'context_tags.*' => ['string'],
            'source' => ['nullable', 'string', 'max:255'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'is_verified' => ['boolean'],
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
