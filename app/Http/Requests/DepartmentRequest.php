<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;

#[IgnoreParam('attached_desks', in: 'body')]
#[IgnoreParam('is_active', in: 'body')]
class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'isactive' => ['sometimes', 'boolean'],
            'desk_ids' => ['sometimes', 'array', 'list', 'max:500'],
            'desk_ids.*' => ['required', 'uuid', 'distinct', 'exists:desks,id'],
            'attached_desks' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }
}
