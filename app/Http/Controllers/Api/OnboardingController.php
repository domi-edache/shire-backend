<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate
        $request->validate([
            'handle' => 'required|string|unique:users,handle,' . $request->user()->id,
            'postcode' => 'required|string',
            'address_line_1' => 'nullable|string',
        ]);

        $user = $request->user();

        // 2. Update Basic Info
        $user->handle = $request->handle;
        $user->postcode = $request->postcode;
        $user->address_line_1 = $request->address_line_1;

        // 3. Mock Geocoding (The Magic Trick)
        // We set everyone to a random spot near Central London so the "Nearby" logic works for testing.
        // In production, you would call a Geocoding API here.

        $lat = 51.5074 + (mt_rand(-100, 100) / 10000); // Random variance
        $lng = -0.1278 + (mt_rand(-100, 100) / 10000);

        // Save using PostGIS raw command
        $user->location = DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)");

        $user->save();

        return response()->json([
            'message' => 'Profile setup complete',
            'user' => $user->fresh()
        ]);
    }
}