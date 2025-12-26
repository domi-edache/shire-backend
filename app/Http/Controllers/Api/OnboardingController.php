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

        // 4. Real Geocoding (The Real Magic)
        $postcode = str_replace(' ', '', $user->postcode);
        try {
            $response = Http::get("https://api.postcodes.io/postcodes/{$postcode}");

            if (!$response->successful()) {
                throw ValidationException::withMessages([
                    'postcode' => ['The provided postcode could not be found. Please enter a valid UK postcode.'],
                ]);
            }

            $result = $response->json('result');
            $latitude = $result['latitude'];
            $longitude = $result['longitude'];

            // Save to PostGIS location column
            $user->location = DB::raw("ST_GeomFromText('POINT($longitude $latitude)', 4326)");
        } catch (\Exception $e) {
            if ($e instanceof ValidationException)
                throw $e;

            \Illuminate\Support\Facades\Log::error('Geocoding failed: ' . $e->getMessage());
            // Fallback: if API is down, we might want to allow it or fail. 
            // For now, let's fail to ensure data integrity.
            throw ValidationException::withMessages([
                'postcode' => ['Unable to verify postcode at this time. Please try again later.'],
            ]);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile setup complete',
            'user' => $user->fresh()
        ]);
    }
}