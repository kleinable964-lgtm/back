<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'latin_name_alias' => $this->latin_name_alias,
            'additional_phone' => $this->additional_phone,
            'register_ip' => $this->register_ip,
            'source' => $this->source,
            'marketing_details' => $this->marketing_details,
            'brand_id' => $this->brand_id,
            'country_id' => $this->country_id,
            'customer_status_id' => $this->customer_status_id,
            'affiliate_id' => $this->affiliate_id,
            'desk_id' => $this->desk_id,
            'signup_status' => $this->signup_status,
            'signup_rejection_reason' => $this->signup_rejection_reason,
            'offering_ipos_enabled' => $this->offering_ipos_enabled,
            'offering_icos_enabled' => $this->offering_icos_enabled,
            'offering_stocks_enabled' => $this->offering_stocks_enabled,
            'offering_commodities_enabled' => $this->offering_commodities_enabled,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'assigned_user_id' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->uuid),
        ];
    }
}
