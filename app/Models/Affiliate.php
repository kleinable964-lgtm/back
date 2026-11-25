<?php

namespace App\Models;

use Database\Factories\AffiliateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Affiliate extends Model
{
    /** @use HasFactory<AffiliateFactory> */
    use HasFactory;

    public $timestamps = false;

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
