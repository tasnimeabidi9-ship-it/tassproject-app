<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Body: { email, password }
     * Returns: { token, user }
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // Revoke old tokens for this device/session name so tokens don't pile up
        $user->tokens()->where('name', 'api-token')->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        $user->forceFill(['last_login' => now()])->save();

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    /**
     * POST /api/auth/logout
     * Revokes the token used to make this request.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        return response()->json(
            $request->user()->only(['id', 'name', 'email', 'role', 'last_login'])
        );
    }
}
