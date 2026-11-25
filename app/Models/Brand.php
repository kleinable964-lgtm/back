<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'domain', 'ai_trading_mode', 'design_version', 'ai_version', 'arbitrage_version', 'festgeld_design_variant', 'festgeld_registration_flow', 'gold_arbitrage_visible', 'disable_crypto_withdrawals', 'disable_wire_withdrawals', 'require_kyc_for_withdrawals', 'clients_see_all_custom_assets', 'transactional_emails_enabled', 'forgot_password_enabled', 'ai_financial_advisor_enabled', 'transactional_email_templates'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['gold_arbitrage_visible' => 'boolean', 'disable_crypto_withdrawals' => 'boolean', 'disable_wire_withdrawals' => 'boolean', 'require_kyc_for_withdrawals' => 'boolean', 'clients_see_all_custom_assets' => 'boolean', 'transactional_emails_enabled' => 'boolean', 'forgot_password_enabled' => 'boolean', 'ai_financial_advisor_enabled' => 'boolean', 'transactional_email_templates' => 'object'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
