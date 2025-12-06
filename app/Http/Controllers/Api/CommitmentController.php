<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RunItem;
use App\Models\RunCommitment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommitmentController extends Controller
{
    /**
     * Store a newly created commitment (transaction).
     */
    public function store(Request $request, RunItem $item)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $quantity = $validated['quantity'];

        // Stock check
        $remaining = $item->units_total - $item->units_filled;

        if ($quantity > $remaining) {
            return response()->json([
                'message' => 'Not enough stock available',
                'available' => $remaining,
                'requested' => $quantity
            ], 400);
        }

        // Use database transaction for atomicity
        try {
            $commitment = DB::transaction(function () use ($request, $item, $quantity) {
                // Create the commitment
                $commitment = RunCommitment::create([
                    'run_item_id' => $item->id,
                    'user_id' => $request->user()->id,
                    'quantity' => $quantity,
                    'total_amount' => $item->cost * $quantity,
                    'status' => 'pending',
                ]);

                // Increment units_filled
                $item->increment('units_filled', $quantity);

                return $commitment;
            });

            // Load relationships for response
            $commitment->load(['item', 'user']);

            return response()->json([
                'data' => $commitment
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create commitment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
