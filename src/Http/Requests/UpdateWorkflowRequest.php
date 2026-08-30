<?php

namespace Aitumalow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'folder_id' => ['nullable', 'integer', 'exists:'.config('aitumalow.tables.folders', 'aitumalow_workflow_folders').',id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:'.config('aitumalow.tables.tags', 'aitumalow_workflow_tags').',id'],
        ];
    }
}
