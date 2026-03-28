<?php

declare(strict_types=1);

namespace Aicl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AiChatRequest.
 */
class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxLength = (int) config('aicl.ai.max_prompt_length', 2000);

        return [
            'message' => ['required', 'string', "max:{$maxLength}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'A message is required.',
            'message.max' => 'The message must not exceed :max characters.',
        ];
    }
}
