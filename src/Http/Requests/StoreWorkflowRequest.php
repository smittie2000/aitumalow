<?php

namespace Aitumalow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:191', 'regex:/\A[a-z][a-z0-9._-]*\z/', 'unique:'.config('aitumalow.tables.workflows', 'aitumalow_workflows').',key'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'folder_id' => ['nullable', 'integer', 'exists:'.config('aitumalow.tables.folders', 'aitumalow_workflow_folders').',id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:'.config('aitumalow.tables.tags', 'aitumalow_workflow_tags').',id'],
            'created_via' => ['nullable', 'string', 'in:editor,import,code,api,duplicate'],
        ];
    }
}
