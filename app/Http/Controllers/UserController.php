<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return UserResource::collection(User::with('desks')->orderBy('id')->paginate($request->validated('per_page', 25)));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('desks'));
    }

    public function store(UserRequest $request): UserResource
    {
        $user = DB::transaction(function () use ($request): User {
            $data = $request->safe()->except(['desk_ids']);
            $user = new User;
            $user->forceFill($data)->save();
            if ($request->has('desk_ids')) {
                $user->desks()->sync($request->validated('desk_ids'));
            }

            return $user;
        });

        return new UserResource($user->refresh()->load('desks'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $data = $request->safe()->except(['desk_ids']);
        if ($user->is($request->user()) && ((array_key_exists('role', $data) && $data['role'] !== 'admin') || (isset($data['status']) && $data['status'] !== 'active'))) {
            throw ValidationException::withMessages([array_key_exists('role', $data) && $data['role'] !== 'admin' ? 'role' : 'status' => ['You cannot remove your own administrative access.']]);
        }
        DB::transaction(function () use ($request, $user, $data): void {
            $user->forceFill($data)->save();
            if ($user->wasChanged(['status', 'role', 'password', 'permission_group'])) {
                $user->tokens()->delete();
            }
            if ($request->has('desk_ids')) {
                $user->desks()->sync($request->validated('desk_ids'));
                $user->touch();
            }
        });

        return new UserResource($user->refresh()->load('desks'));
    }
}
