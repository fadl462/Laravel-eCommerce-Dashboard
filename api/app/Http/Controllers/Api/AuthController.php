<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // Deliberately vague — never reveal whether the email exists.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This administrator account has been suspended.'],
            ]);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->activityLogger->log($user, 'Signed in', 'Authentication');

        // Sanctum personal access token — the dashboard stores this and sends
        // it as `Authorization: Bearer <token>` on every subsequent request.
        $token = $user->createToken('dashboard', ['*'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->display_name,
                'permissions' => $user->role
                    ? $user->role->permissions()->pluck('key')
                    : [],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        $this->activityLogger->log($request->user(), 'Signed out', 'Authentication');

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('role.permissions');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->display_name,
            'permissions' => $user->role?->permissions->pluck('key') ?? [],
        ]);
    }
}
