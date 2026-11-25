<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label'])]
class Role extends Model
{
    public const array DEFINITIONS = ['admin' => 'Admin', 'manager' => 'Manager', 'agent' => 'Agent'];

    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    public $incrementing = false;

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'name');
    }
}
