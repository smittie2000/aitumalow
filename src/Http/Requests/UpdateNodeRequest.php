<?php

namespace Aitumalow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'position_x' => ['sometimes', 'integer'],
            'position_y' => ['sometimes', 'integer'],
        ];
    }
}
