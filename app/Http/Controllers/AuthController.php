<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();
        if (! Hash::check($request->validated('password'), $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.')) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }
        if (! $user) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }
        abort_unless($user->status === 'active', 403, 'Your CRM account is not active.');
        $expiresAt = now()->addHours(12);
        $token = $user->createToken($request->validated('device_name', 'frontend'), ['*'], $expiresAt);
        User::withoutTimestamps(fn () => $user->forceFill(['last_login_at' => now()])->save());

        return response()->json(['token_type' => 'Bearer', 'access_token' => $token->plainTextToken, 'expires_at' => $expiresAt->toISOString(), 'data' => new UserResource($user->load('desks'))]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('desks'));
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
