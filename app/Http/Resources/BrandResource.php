<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'ai_trading_mode' => $this->ai_trading_mode,
            'design_version' => $this->design_version,
            'ai_version' => $this->ai_version,
            'arbitrage_version' => $this->arbitrage_version,
            'festgeld_design_variant' => $this->festgeld_design_variant,
            'festgeld_registration_flow' => $this->festgeld_registration_flow,
            'gold_arbitrage_visible' => $this->gold_arbitrage_visible,
            'disable_crypto_withdrawals' => $this->disable_crypto_withdrawals,
            'disable_wire_withdrawals' => $this->disable_wire_withdrawals,
            'require_kyc_for_withdrawals' => $this->require_kyc_for_withdrawals,
            'clients_see_all_custom_assets' => $this->clients_see_all_custom_assets,
            'transactional_emails_enabled' => $this->transactional_emails_enabled,
            'forgot_password_enabled' => $this->forgot_password_enabled,
            'ai_financial_advisor_enabled' => $this->ai_financial_advisor_enabled,
            'transactional_email_templates' => $this->transactional_email_templates,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

        ];
    }
}
