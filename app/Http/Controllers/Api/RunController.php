<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Run;
use App\Models\RunItem;
use App\Models\RunCommitment;
use App\Models\RunActivity;
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
            'is_taking_requests' => 'nullable|boolean',

            // Anchor Item (Optional)
            'anchor_title' => 'nullable|string|max:255',
            'anchor_total_cost' => 'nullable|numeric|min:0|required_with:anchor_title',
            'anchor_slots' => 'nullable|integer|min:1|required_with:anchor_title',
            'host_slots' => 'nullable|integer|min:1|lte:anchor_slots',
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

        // 2. Handle Instructions (The Memory)
        if ($request->filled('pickup_instructions')) {
            $user->default_pickup_instructions = $request->pickup_instructions;
        }
        $pickupInstructions = $request->pickup_instructions ?? $user->default_pickup_instructions;

        if ($request->filled('payment_instructions')) {
            $user->default_payment_instructions = $request->payment_instructions;
        }
        $paymentInstructions = $request->payment_instructions ?? $user->default_payment_instructions;

        if ($user->isDirty()) {
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

        try {
            $run = DB::transaction(function () use ($user, $validated, $pickupImagePath, $pickupInstructions, $paymentInstructions, $userLocation, $request) {
                // 4. Create the run
                $expiresIn = (int) ($validated['expires_in'] ?? 60);

                $run = Run::create([
                    'user_id' => $user->id,
                    'store_name' => $validated['store_name'],
                    'expires_at' => now()->addMinutes($expiresIn),
                    'status' => 'prepping',
                    'runner_fee' => 0, // Constraint: Free for V1
                    'runner_fee_type' => 'free',
                    'pickup_image_path' => $pickupImagePath,
                    'pickup_instructions' => $pickupInstructions,
                    'payment_instructions' => $paymentInstructions,
                    'is_taking_requests' => $request->boolean('is_taking_requests'),
                    'location' => $userLocation->location,
                ]);

                // 5. Handle Anchor Item (The Bulk Split)
                if ($request->filled('anchor_title')) {
                    $hostSlots = $validated['host_slots'] ?? 1;

                    $item = $run->items()->create([
                        'title' => $validated['anchor_title'],
                        'type' => 'bulk_split',
                        'units_total' => $validated['anchor_slots'],
                        'units_filled' => $hostSlots,
                        'cost' => $validated['anchor_total_cost'],
                        'status' => 'pending',
                    ]);

                    // 6. Auto-Commit the Host
                    RunCommitment::create([
                        'run_item_id' => $item->id,
                        'user_id' => $user->id,
                        'quantity' => $hostSlots,
                        'total_amount' => 0, // Host doesn't pay themselves
                        'status' => 'confirmed',
                        'payment_status' => 'confirmed',
                    ]);

                    // 7. Log Activity
                    $run->activities()->create([
                        'user_id' => $user->id,
                        'type' => 'host_auto_join',
                        'metadata' => [
                            'slots' => $hostSlots,
                            'item_title' => $item->title,
                        ],
                    ]);
                }

                return $run;
            });

            // Reload to get the location and items
            $run->load(['user', 'items']);
            $run->distance_string = "0.0km away";

            return new RunResource($run);

        } catch (\Exception $e) {
            \Log::error('Haul creation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Failed to create haul',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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

        // Load relationships needed for privacy logic and participants list
        $run->load(['user', 'items.commitments.user']);

        // Check if I have a commitment (My Status)
        // We look through all items -> commitments to find one belonging to the auth user.
        $myCommitment = null;
        foreach ($run->items as $item) {
            $commitment = $item->commitments->where('user_id', $user->id)->first();
            if ($commitment) {
                $myCommitment = $commitment;
                break;
            }
        }
        $run->my_commitment = $myCommitment;

        return new RunResource($run);
    }

    /**
     * Display runs that the authenticated user is involved in.
     */
    public function myHauls(Request $request)
    {
        $user = $request->user();

        // 1. Find runs where user is host OR has a commitment
        $runs = Run::query()
            ->where('user_id', $user->id)
            ->orWhereHas('items.commitments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['user', 'items.commitments.user'])
            // 2. Sort Logic: Active statuses first, then by recency
            ->orderByRaw("
                CASE 
                    WHEN status IN ('prepping', 'live', 'heading_back', 'arrived') THEN 1
                    ELSE 2
                END ASC
            ")
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. Attach my_commitment for each run (so RunResource can pick it up)
        $runs->each(function ($run) use ($user) {
            $myCommitment = null;
            foreach ($run->items as $item) {
                $commitment = $item->commitments->where('user_id', $user->id)->first();
                if ($commitment) {
                    $myCommitment = $commitment;
                    break;
                }
            }
            $run->my_commitment = $myCommitment;
            $run->distance_string = $run->user_id === $user->id ? "Hosting" : "Joined";
        });

        return RunResource::collection($runs);
    }

    /**
     * Cancel/Delete a run (Host only).
     */
    public function destroy(Request $request, Run $run)
    {
        $user = $request->user();

        // Only the host can cancel
        if ($run->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only the host can cancel this haul.'
            ], 403);
        }

        // Safety Check: Check for confirmed payments from OTHER users
        $run->load('items.commitments');
        $hasConfirmedPayments = false;

        foreach ($run->items as $item) {
            foreach ($item->commitments as $commitment) {
                // Skip host's own commitment
                if ($commitment->user_id === $run->user_id) {
                    continue;
                }
                // Check for confirmed status
                if ($commitment->status === 'confirmed') {
                    $hasConfirmedPayments = true;
                    break 2;
                }
            }
        }

        if ($hasConfirmedPayments) {
            return response()->json([
                'message' => 'You cannot cancel. You have confirmed payments. Please refund/remove users first.'
            ], 422);
        }

        try {
            DB::transaction(function () use ($run, $user) {
                // Log activity before deletion
                RunActivity::create([

                    'run_id' => $run->id,
                    'user_id' => $user->id,
                    'type' => 'run_cancelled',
                    'metadata' => [
                        'store_name' => $run->store_name,
                    ],
                ]);

                // Delete the run (cascading deletes items, commitments, chats, activities)
                $run->delete();
            });

            return response()->json(['message' => 'Haul cancelled successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel haul',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
