<?php

declare(strict_types=1);

namespace Aicl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateAiConversationRequest.
 */
class UpdateAiConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_pinned' => ['sometimes', 'boolean'],
            'state' => ['sometimes', 'string'],
        ];
    }
}
