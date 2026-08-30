<?php

namespace Aitumalow\Http\Requests;

use Aitumalow\Registry\NodeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'node_key' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'position_x' => ['integer'],
            'position_y' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $registry = app(NodeRegistry::class);

            if (! $registry->has($this->input('node_key', ''))) {
                $validator->errors()->add('node_key', 'Unknown node key: '.$this->input('node_key'));
            }
        });
    }
}
