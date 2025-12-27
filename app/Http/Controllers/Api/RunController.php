<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Run;
use App\Http\Resources\RunResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RunController extends Controller
{
    /**
     * Display a listing of runs near the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Determine Coordinates (Request > User Profile)
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (!$lat || !$lng) {
            $location = DB::selectOne(
                "SELECT ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng FROM users WHERE id = ?",
                [$user->id]
            );
            $lat = $location->lat ?? null;
            $lng = $location->lng ?? null;
        }

        if (!$lat || !$lng) {
            return response()->json([
                'message' => 'Location coordinates required',
                'data' => []
            ], 200);
        }

        $radius = $request->query('radius', 2000);

        // 2. Query runs within radius using the nearby scope
        // Filter by status: prepping or live
        $runs = Run::query()
            ->select('*')
            ->selectRaw(
                "ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance_meters",
                [$lng, $lat]
            )
            ->whereIn('status', ['prepping', 'live'])
            ->nearby($lat, $lng, $radius)
            ->with(['user', 'items'])
            ->orderBy('distance_meters')
            ->get();

        // 3. Attach distance string for the Resource to use
        $runs->each(function ($run) {
            $km = round($run->distance_meters / 1000, 1);
            $run->distance_string = "{$km}km away";
        });

        return RunResource::collection($runs);
    }

    /**
     * Store a newly created run.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'expires_in' => 'required|integer|min:1', // Minutes
            'pickup_image' => 'nullable|file|image|max:10240', // 10MB max
            'pickup_instructions' => 'nullable|string',
            'payment_instructions' => 'nullable|string',

            // Anchor Item (Optional)
            'anchor_title' => 'nullable|string|max:255',
            'anchor_total_cost' => 'nullable|numeric|min:0|required_with:anchor_title',
            'anchor_slots' => 'nullable|integer|min:1|required_with:anchor_title',
        ]);

        $user = $request->user();

        // 1. Handle Pickup Image (The Memory)
        $pickupImagePath = $user->default_pickup_image_path;

        if ($request->hasFile('pickup_image')) {
            // Upload new and update default
            $path = $request->file('pickup_image')->store('pickups', 'public');
            $user->default_pickup_image_path = $path;
            $user->save();
            $pickupImagePath = $path;
        } elseif (!$pickupImagePath) {
            // No new upload and no default exists
            return response()->json([
                'message' => 'A pickup location photo is required for your first run.',
                'errors' => ['pickup_image' => ['Required for first run']]
            ], 422);
        }

        // 2. Handle Instructions
        $pickupInstructions = $request->pickup_instructions ?? $user->default_pickup_instructions;

        if ($request->filled('pickup_instructions')) {
            $user->default_pickup_instructions = $request->pickup_instructions;
            $user->save();
        }

        // 3. Get user's location
        $userLocation = DB::selectOne(
            "SELECT location FROM users WHERE id = ?",
            [$user->id]
        );

        if (!$userLocation || !$userLocation->location) {
            return response()->json([
                'message' => 'User location must be set before creating a run'
            ], 400);
        }

        // 4. Create the run
        $run = Run::create([
            'user_id' => $user->id,
            'store_name' => $validated['store_name'],
            'expires_at' => now()->addMinutes($validated['expires_in']),
            'status' => 'prepping',
            'runner_fee' => 0, // Constraint: Free for V1
            'runner_fee_type' => 'free',
            'pickup_image_path' => $pickupImagePath,
            'pickup_instructions' => $pickupInstructions,
            'payment_instructions' => $validated['payment_instructions'] ?? null,
            'location' => $userLocation->location, // Direct assignment from DB select
        ]);

        // 5. Handle Anchor Item (The Bulk Split)
        if ($request->filled('anchor_title')) {
            $run->items()->create([
                'run_id' => $run->id,
                'title' => $validated['anchor_title'],
                'type' => 'bulk_split',
                'units_total' => $validated['anchor_slots'],
                'units_filled' => 0,
                'cost' => $validated['anchor_total_cost'],
                'status' => 'pending',
            ]);
        }

        // Reload to get the location and items
        $run->refresh();
        $run->load(['user', 'items']);

        // Set distance to 0 for the creator
        $run->distance_string = "0.0km away";

        return new RunResource($run);
    }

    /**
     * Display the specified run.
     */
    public function show(Request $request, Run $run)
    {
        $user = $request->user();

        // Get user's location coordinates
        $location = DB::selectOne(
            "SELECT ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng FROM users WHERE id = ?",
            [$user->id]
        );

        if ($location && $location->lat && $location->lng) {
            // Calculate distance
            $distance = DB::selectOne(
                "SELECT ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as dist FROM runs WHERE id = ?",
                [$location->lng, $location->lat, $run->id]
            );

            if ($distance) {
                $km = round($distance->dist / 1000, 1);
                $run->distance_string = "{$km}km away";
            }
        }

        $run->load(['user', 'items.commitments']);

        return new RunResource($run);
    }
}
