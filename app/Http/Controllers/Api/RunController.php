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

        // Get user's location coordinates
        $location = DB::selectOne(
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
        $pickupInstructions = $user->default_pickup_instructions;

        if ($request->filled('pickup_instructions')) {
            $user->default_pickup_instructions = $request->pickup_instructions;
            $user->save();
            $pickupInstructions = $request->pickup_instructions;
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
        ]);

        // Copy user's location to the run
        DB::statement(
            "UPDATE runs SET location = (SELECT location FROM users WHERE id = ?) WHERE id = ?",
            [$user->id, $run->id]
        );

        // 5. Handle Anchor Item (The Bulk Split)
        if ($request->filled('anchor_title')) {
            $costPerUnit = round($validated['anchor_total_cost'] / $validated['anchor_slots'], 2);

            $run->items()->create([
                'user_id' => $user->id,
                'name' => $validated['anchor_title'],
                'type' => 'bulk_split',
                'units_total' => $validated['anchor_slots'],
                'units_allocated' => 0, // Starts empty
                'cost_per_unit' => $costPerUnit,
                'image_path' => null, // Optional for anchor items
            ]);
        }

        // Reload to get the location and items
        $run->refresh();
        $run->load('items');

        return response()->json([
            'data' => $run
        ], 201);
    }

    /**
     * Display the specified run.
     */
    public function show(Run $run)
    {
        $run->load(['user', 'items.commitments', 'activities.user']);

        return new RunResource($run);
    }
}
