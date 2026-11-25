<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    public function updatePreferences(UpdatePreferencesRequest $request): UserResource
    {
        $user = $request->user();
        $user->forceFill($request->safe()->only(['timezone']))->save();

        return new UserResource($user->refresh()->load('desks'));
    }
}
