<?php

// PATTERN: Separate Form Request classes for Store and Update.
// PATTERN: authorize() checks permissions via policy.
// PATTERN: rules() returns array-based validation (not pipe-separated strings).
// PATTERN: messages() provides custom error messages.

// --- StoreProjectRequest ---

namespace Aicl\Http\Requests;

use Aicl\Enums\ProjectPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    // PATTERN: authorize() delegates to the policy.
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Project::class);
    }

    /**
     * PATTERN: Array-based rules (not pipe-separated strings).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // PATTERN: Enum validation with Rule::enum().
            'priority' => ['required', Rule::enum(ProjectPriority::class)],
            'start_date' => ['nullable', 'date'],
            // PATTERN: Cross-field validation with 'after:field'.
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            // PATTERN: Array of IDs for many-to-many relationships.
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after' => 'The end date must be after the start date.',
            'member_ids.*.exists' => 'One or more selected members do not exist.',
        ];
    }
}

// --- UpdateProjectRequest ---
// PATTERN: Update uses 'sometimes' for partial updates.

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // PATTERN: $this->route('entity_name') gets the bound model.
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // PATTERN: 'sometimes' makes the field optional in PATCH requests.
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'required', Rule::enum(ProjectPriority::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after' => 'The end date must be after the start date.',
            'member_ids.*.exists' => 'One or more selected members do not exist.',
        ];
    }
}
