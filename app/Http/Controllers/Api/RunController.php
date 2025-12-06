<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Run;
use Illuminate\Http\Request;

class RunController extends Controller
{
    /**
     * Display a listing of runs near the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get user's location coordinates
        $location = \DB::selectOne(
            "SELECT ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng FROM users WHERE id = ?",
            [$user->id]
        );

        if (!$location || !$location->lat || !$location->lng) {
            return response()->json([
                'message' => 'User location not set',
                'data' => []
            ], 200);
        }

        // Query runs within 2000 meters using the nearby scope
        $runs = Run::query()
            ->nearby($location->lat, $location->lng, 2000)
            ->with(['user', 'items'])
            ->get();

        return response()->json([
            'data' => $runs
        ]);
    }

    /**
     * Store a newly created run.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'expires_at' => 'required|date|after:now',
        ]);

        $user = $request->user();

        // Get user's location
        $userLocation = \DB::selectOne(
            "SELECT location FROM users WHERE id = ?",
            [$user->id]
        );

        if (!$userLocation || !$userLocation->location) {
            return response()->json([
                'message' => 'User location must be set before creating a run'
            ], 400);
        }

        // Create the run
        $run = Run::create([
            'user_id' => $user->id,
            'store_name' => $validated['store_name'],
            'expires_at' => $validated['expires_at'],
            'status' => 'prepping',
        ]);

        // Copy user's location to the run
        \DB::statement(
            "UPDATE runs SET location = (SELECT location FROM users WHERE id = ?) WHERE id = ?",
            [$user->id, $run->id]
        );

        // Reload to get the location
        $run->refresh();

        return response()->json([
            'data' => $run
        ], 201);
    }
}
