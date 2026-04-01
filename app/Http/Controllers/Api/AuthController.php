<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a Sanctum API token.
     *
     * POST /api/auth/login
     * Body: { email, password, device_name? }
     *
     * Returns: { token, user, abilities }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status != 1) {
            return response()->json(['message' => 'Your account is inactive.'], 403);
        }

        if ($user->isLocked()) {
            return response()->json(['message' => 'Account locked due to too many failed attempts. Try again later.'], 423);
        }

        $user->resetFailedAttempts();

        $deviceName = $request->input('device_name', $request->userAgent() ?? 'api-client');

        // Role-based token abilities
        $abilities = match ($user->role) {
            'admin'      => ['*'],
            'manager'    => ['leads:read', 'leads:write', 'campaigns:read', 'campaigns:write', 'reports:read'],
            'telecaller' => ['leads:read', 'leads:write'],
            default      => ['leads:read'],
        };

        // Revoke any existing tokens for this device to prevent accumulation
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName, $abilities);

        return response()->json([
            'token'     => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
            'user'      => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'abilities' => $abilities,
        ]);
    }

    /**
     * Return the authenticated user's profile.
     *
     * GET /api/auth/me
     * Header: Authorization: Bearer {token}
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'is_online'  => $user->is_online,
            'abilities'  => $user->currentAccessToken()->abilities ?? [],
            'token_name' => $user->currentAccessToken()->name ?? null,
        ]);
    }

    /**
     * Revoke the current token (logout).
     *
     * POST /api/auth/logout
     * Header: Authorization: Bearer {token}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Revoke all tokens for this user (logout from all devices).
     *
     * POST /api/auth/logout-all
     * Header: Authorization: Bearer {token}
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }
}
