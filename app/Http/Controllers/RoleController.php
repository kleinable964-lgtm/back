<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    /** List the three supported roles for coworker assignment dropdowns. Only active coworkers may read this list. */
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::query()->orderBy('name')->get());
    }
}
