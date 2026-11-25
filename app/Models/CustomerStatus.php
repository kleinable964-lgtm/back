<?php

namespace App\Models;

use Database\Factories\CustomerStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color', 'is_default', 'disabled_for', 'is_ftd_status', 'order_weight'])]
class CustomerStatus extends Model
{
    /** @use HasFactory<CustomerStatusFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'disabled_for' => 'array', 'is_ftd_status' => 'boolean', 'order_weight' => 'integer'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
