<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
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
            'domain' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/', Rule::unique('brands')->ignore($this->route('brand'))],
            'ai_trading_mode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'design_version' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ai_version' => ['sometimes', 'nullable', 'string', 'max:255'],
            'arbitrage_version' => ['sometimes', 'nullable', 'string', 'max:255'],
            'festgeld_design_variant' => ['sometimes', 'nullable', 'string', 'max:255'],
            'festgeld_registration_flow' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gold_arbitrage_visible' => ['sometimes', 'boolean'],
            'disable_crypto_withdrawals' => ['sometimes', 'boolean'],
            'disable_wire_withdrawals' => ['sometimes', 'boolean'],
            'require_kyc_for_withdrawals' => ['sometimes', 'boolean'],
            'clients_see_all_custom_assets' => ['sometimes', 'boolean'],
            'transactional_emails_enabled' => ['sometimes', 'boolean'],
            'forgot_password_enabled' => ['sometimes', 'boolean'],
            'ai_financial_advisor_enabled' => ['sometimes', 'boolean'],
            'transactional_email_templates' => ['sometimes', 'nullable', 'array', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && $value !== [] && array_is_list($value)) {
                    $fail('The :attribute must be an object keyed by template name.');
                }
            }],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge(['domain' => strtolower(trim($this->input('domain')))]);
        }
    }
}
