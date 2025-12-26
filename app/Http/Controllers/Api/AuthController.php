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
            'provider' => 'required|in:google,apple',
            'provider_id' => 'required|string',
            'name' => 'nullable|string',
            'avatar_url' => 'nullable|url',
            'token' => 'nullable|string', // OAuth token (mocked for now)
        ]);

        // 2. Find User by Email
        $user = User::where('email', $request->email)->first();

        // 3. If User doesn't exist, create one
        if (!$user) {
            $user = new User();
            $user->email = $request->email;
            $user->password = null; // Social users don't have passwords
        }

        // 4. Update name if provided and not already set
        if ($request->name && empty($user->name)) {
            $user->name = $request->name;
        }

        // 5. Handle Avatar Download
        if ($request->avatar_url) {
            $user->avatar_url = $request->avatar_url; // Save raw URL
            try {
                $response = \Illuminate\Support\Facades\Http::get($request->avatar_url);
                if ($response->successful()) {
                    $extension = 'jpg'; // Default to jpg
                    $filename = 'avatars/' . uniqid() . '.' . $extension;
                    \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $response->body());

                    // Delete old avatar if it exists
                    if ($user->avatar_path) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
                    }

                    $user->avatar_path = $filename;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to download social avatar: ' . $e->getMessage());
            }
        }

        // 6. Update the specific ID based on provider
        if ($request->provider === 'google') {
            $user->google_id = $request->provider_id;
        } elseif ($request->provider === 'apple') {
            $user->apple_id = $request->provider_id;
        }

        $user->save();

        // 6. Generate Token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // 7. Check Onboarding Status
        // They need onboarding if they don't have a Name OR a Handle OR a Postcode
        $needsOnboarding = empty($user->name) || empty($user->handle) || empty($user->postcode);

        return response()->json([
            'token' => $token,
            'user' => $user,
            'needs_onboarding' => $needsOnboarding
        ]);
    }

    public function checkHandle(Request $request)
    {
        $request->validate([
            'handle' => 'required|string|min:3|max:30|regex:/^[a-zA-Z0-9_]+$/',
        ]);

        $exists = User::where('handle', $request->handle)->exists();

        return response()->json([
            'available' => !$exists
        ]);
    }
}
