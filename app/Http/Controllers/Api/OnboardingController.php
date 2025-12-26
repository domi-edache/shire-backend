<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Onboarding Attempt', [
            'user_id' => $request->user()?->id,
            'payload' => $request->all()
        ]);

        // 1. Validate
        $request->validate([
            'name' => 'required|string|max:255',
            'handle' => 'required|string|unique:users,handle,' . $request->user()->id,
            'postcode' => 'required|string',
            'address_line_1' => 'nullable|string',
            'image' => 'nullable|image|max:5120', // Max 5MB
            'device_lat' => 'nullable|numeric',
            'device_lng' => 'nullable|numeric',
        ], [
            'handle.unique' => 'That handle is already taken. Try another?',
            'required' => 'Don\'t forget your :attribute!',
        ]);

        $user = $request->user();

        // 2. Update Basic Info
        $user->name = $request->name;
        $user->handle = $request->handle;
        $user->postcode = $request->postcode;
        $user->address_line_1 = $request->address_line_1;

        // 3. Shadow Location Logic (Fraud/Analytics)
        if ($request->device_lat && $request->device_lng) {
            $user->signup_device_location = DB::raw("ST_GeomFromText('POINT({$request->device_lng} {$request->device_lat})', 4326)");
        }

        // 4. Handle Image Upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('avatars', 'public');

            // Delete old avatar if it exists
            if ($user->avatar_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $path;
        }

        $warning = null;
        // 4. Real Geocoding (The Real Magic)
        $postcode = str_replace(' ', '', $user->postcode);
        try {
            $response = Http::get("https://api.postcodes.io/postcodes/{$postcode}");

            if ($response->successful()) {
                $result = $response->json('result');
                $latitude = $result['latitude'];
                $longitude = $result['longitude'];
                $user->location = DB::raw("ST_GeomFromText('POINT($longitude $latitude)', 4326)");
            } elseif ($response->status() === 404) {
                // User typo - this SHOULD block them
                throw ValidationException::withMessages([
                    'postcode' => ['Hmm, we can\'t find that postcode.'],
                ]);
            } else {
                // Other API error (500, etc) - trigger graceful degradation
                throw new \Exception("Postcodes.io returned status: " . $response->status());
            }
        } catch (\Exception $e) {
            // If it's a validation exception (404), re-throw it
            if ($e instanceof ValidationException)
                throw $e;

            // Log the technical error for debugging
            \Illuminate\Support\Facades\Log::warning('Geocoding service unreachable, falling back: ' . $e->getMessage());
            $warning = "We couldn't pinpoint your location, but you're all signed up! You can update your postcode later in Profile.";

            // GRACEFUL DEGRADATION
            // Fallback 1: Use Device GPS if available
            if ($request->device_lat && $request->device_lng) {
                $user->location = DB::raw("ST_GeomFromText('POINT({$request->device_lng} {$request->device_lat})', 4326)");
            } else {
                // Fallback 2: Default to Central London (POINT(-0.1278 51.5074))
                $user->location = DB::raw("ST_GeomFromText('POINT(-0.1278 51.5074)', 4326)");
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profile setup complete',
            'user' => $user->fresh(),
            'warning' => $warning
        ]);
    }
}