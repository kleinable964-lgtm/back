<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CustomerStatusListRequest extends ListRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'available_for' => ['sometimes', Rule::in(['conversion', 'retention'])],
        ];
    }
}
