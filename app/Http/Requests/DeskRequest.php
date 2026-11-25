<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'], 'category' => ['sometimes', 'nullable', 'string', 'max:255'], 'department_id' => ['sometimes', 'nullable', 'uuid', 'exists:departments,id'], 'is_active' => ['sometimes', 'boolean']];
    }
}
