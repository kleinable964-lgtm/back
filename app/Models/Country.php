<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'iso_code'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
