<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[IgnoreParam('id', in: 'body')]
#[IgnoreParam('created_at', in: 'body')]
#[IgnoreParam('updated_at', in: 'body')]
class CustomerStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            /**
             * Six-digit hexadecimal color.
             *
             * @example #22c55e
             */
            'color' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'is_default' => ['sometimes', 'boolean'],
            'disabled_for' => ['sometimes', 'array', 'list', 'max:2'],
            'disabled_for.*' => ['required', 'string', 'distinct', Rule::in(['conversion', 'retention'])],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('color'))) {
            $this->merge(['color' => strtolower($this->input('color'))]);
        }
    }
}
