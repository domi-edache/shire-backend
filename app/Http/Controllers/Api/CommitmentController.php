<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RunItem;
use App\Models\RunCommitment;
use App\Models\RunActivity;
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

        // Use database transaction for atomicity and locking
        try {
            $commitment = DB::transaction(function () use ($request, $item, $quantity) {
                // Lock the row to prevent race conditions
                $lockedItem = RunItem::where('id', $item->id)->lockForUpdate()->first();

                // Stock check inside the lock
                $remaining = $lockedItem->units_total - $lockedItem->units_filled;

                if ($quantity > $remaining) {
                    // Throw specific exception to be caught
                    throw new \Exception("Not enough stock available", 422);
                }

                // Create the commitment
                $commitment = RunCommitment::create([
                    'run_item_id' => $lockedItem->id,
                    'user_id' => $request->user()->id,
                    'quantity' => $quantity,
                    'total_amount' => $lockedItem->cost * $quantity,
                    'status' => 'pending',
                ]);

                // Increment units_filled (on the locked item)
                $lockedItem->increment('units_filled', $quantity);

                return $commitment;
            });

            // Load relationships for response
            $commitment->load(['item', 'user']);

            return response()->json([
                'data' => $commitment
            ], 201);

        } catch (\Exception $e) {
            $status = $e->getCode() === 422 ? 422 : 500;
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $status === 500 ? $e->getMessage() : null
            ], $status);
        }
    }

    /**
     * Remove a commitment (Leave or Kick).
     */
    public function destroy(Request $request, RunCommitment $commitment)
    {
        $user = $request->user();
        $item = $commitment->item;
        $run = $item->run;

        // Authorization Logic
        $isOwner = $commitment->user_id === $user->id;
        $isHost = $run->user_id === $user->id;
        $isHostCommitment = $commitment->user_id === $run->user_id;

        // Host cannot kick themselves (they must cancel the run)
        if ($isHost && $isHostCommitment) {
            return response()->json([
                'message' => 'Host cannot leave their own haul. Cancel the haul instead.'
            ], 403);
        }

        // Allow if owner (leaving) or host (kicking)
        if (!$isOwner && !$isHost) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Time-based and payment status restrictions for participants leaving
        if ($isOwner && !$isHost) {
            // Check if payment has been marked
            if ($commitment->payment_status !== 'unpaid') {
                return response()->json([
                    'message' => 'Cannot leave after marking payment. Contact the host.'
                ], 403);
            }

            // Check 30-minute window
            $joinedAt = $commitment->created_at;
            $thirtyMinutesAgo = now()->subMinutes(30);
            if ($joinedAt < $thirtyMinutesAgo) {
                return response()->json([
                    'message' => 'Cannot leave after 30 minutes. Contact the host.',
                    'joined_at' => $joinedAt->toIso8601String(),
                    'window_expired_at' => $joinedAt->addMinutes(30)->toIso8601String()
                ], 403);
            }
        }

        try {
            DB::transaction(function () use ($commitment, $item, $run, $user, $isOwner) {
                // Decrement units_filled
                $item->decrement('units_filled', $commitment->quantity);

                // Log Activity
                $targetUserName = $commitment->user ? $commitment->user->name : 'A user';
                $activityType = $isOwner ? 'user_left' : 'user_kicked';
                $message = $isOwner
                    ? "{$targetUserName} left the haul"
                    : "Host removed {$targetUserName}";

                RunActivity::create([
                    'run_id' => $run->id,
                    'user_id' => $user->id,
                    'type' => $activityType,
                    'metadata' => [
                        'target_user_id' => $commitment->user_id,
                        'target_user_name' => $targetUserName,
                        'quantity' => $commitment->quantity,
                    ],
                ]);

                // Delete the commitment
                $commitment->delete();
            });

            return response()->json(['message' => 'Commitment removed successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove commitment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
