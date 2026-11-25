<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'latin_name_alias', 'additional_phone', 'register_ip', 'source', 'marketing_details', 'brand_id', 'country_id', 'customer_status_id', 'affiliate_id', 'desk_id', 'assigned_user_id', 'signup_status', 'signup_rejection_reason', 'offering_ipos_enabled', 'offering_icos_enabled', 'offering_stocks_enabled', 'offering_commodities_enabled'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['offering_ipos_enabled' => 'boolean', 'offering_icos_enabled' => 'boolean', 'offering_stocks_enabled' => 'boolean', 'offering_commodities_enabled' => 'boolean'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function customerStatus(): BelongsTo
    {
        return $this->belongsTo(CustomerStatus::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
