<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Rules\PasswordByteLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => [$required, 'string', 'max:72', new PasswordByteLength, 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role' => ['sometimes', 'nullable', Rule::in(array_keys(Role::DEFINITIONS)), Rule::exists('roles', 'name')],
            'permission_group' => ['sometimes', 'nullable', Rule::in(['admin', 'conversion-agent', 'retention-agent', 'conversion-tl', 'retention-tl'])],
            'desk_ids' => ['sometimes', 'array', 'list', 'max:500'],
            'desk_ids.*' => ['required', 'uuid', 'distinct', 'exists:desks,id'],
            'status' => ['sometimes', Rule::in(['pending', 'active', 'disabled'])],
            'uuid' => ['prohibited'],
            'last_login_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->isMethod('POST') && ($this->input('password') === '' || $this->input('password') === null)) {
            $this->request->remove('password');
            if ($this->isJson()) {
                $this->json()->remove('password');
            }
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}
