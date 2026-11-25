<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
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
            'first_name' => [$required, 'string', 'max:255'], 'last_name' => [$required, 'string', 'max:255'], 'email' => [$required, 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'additional_phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latin_name_alias' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'register_ip' => ['sometimes', 'nullable', 'ip'], 'marketing_details' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'brand_id' => [$required, 'uuid', 'exists:brands,id'],
            'country_id' => ['sometimes', 'nullable', 'uuid', 'exists:countries,id'],
            'customer_status_id' => ['sometimes', 'nullable', 'uuid', 'exists:customer_statuses,id'],
            'affiliate_id' => ['sometimes', 'nullable', 'integer', 'exists:affiliates,id'],
            'desk_id' => ['sometimes', 'nullable', 'uuid', 'exists:desks,id'],
            'assigned_user_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,uuid'],
            'signup_status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'signup_rejection_reason' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'offering_ipos_enabled' => ['sometimes', 'boolean'],
            'offering_icos_enabled' => ['sometimes', 'boolean'],
            'offering_stocks_enabled' => ['sometimes', 'boolean'],
            'offering_commodities_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
