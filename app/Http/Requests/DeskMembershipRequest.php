<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeskMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['user_ids' => ['present', 'array', 'max:500'], 'user_ids.*' => ['required', 'uuid', 'distinct', 'exists:users,uuid']];
    }
}
