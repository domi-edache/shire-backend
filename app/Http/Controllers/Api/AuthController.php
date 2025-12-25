<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function socialLogin(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            'email' => 'required|email',
            'provider' => 'required|in:google,apple', // Accept both
            'provider_id' => 'required|string',
        ]);

        // 2. Find User by Email
        $user = User::where('email', $request->email)->first();

        // 3. If User doesn't exist, create one
        if (!$user) {
            $user = new User();
            $user->email = $request->email;
            $user->password = null; // Social users don't have passwords
        }

        // 4. Update the specific ID based on provider
        // This ensures if an existing user logs in with Apple later, we link it.
        if ($request->provider === 'google') {
            $user->google_id = $request->provider_id;
        } elseif ($request->provider === 'apple') {
            $user->apple_id = $request->provider_id;
        }

        $user->save();

        // 5. Generate Token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // 6. Check Onboarding Status
        // They need onboarding if they don't have a Handle OR a Postcode
        $needsOnboarding = empty($user->handle) || empty($user->postcode);

        return response()->json([
            'token' => $token,
            'user' => $user,
            'needs_onboarding' => $needsOnboarding
        ]);
    }
}
