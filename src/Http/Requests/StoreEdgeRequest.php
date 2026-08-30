<?php

namespace Aitumalow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEdgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_node_id' => ['required', 'integer'],
            'source_port' => ['string', 'max:50'],
            'target_node_id' => ['required', 'integer'],
            'target_port' => ['string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('source_node_id') === $this->input('target_node_id')) {
                $validator->errors()->add('target_node_id', 'Source and target nodes must be different.');
            }
        });
    }
}
