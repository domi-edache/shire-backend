<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RunCommitment;
use Illuminate\Http\Request;

class HandshakeController extends Controller
{
    /**
     * Mark commitment as paid by the buyer.
     */
    public function markPaid(Request $request, RunCommitment $commitment)
    {
        // Authorization: Only the commitment owner can mark as paid
        if ($request->user()->id !== $commitment->user_id) {
            return response()->json([
                'message' => 'Unauthorized. Only the buyer can mark as paid.'
            ], 403);
        }

        $commitment->update([
            'payment_status' => 'paid_marked'
        ]);

        return response()->json([
            'message' => 'Payment marked as paid',
            'data' => $commitment
        ]);
    }

    /**
     * Confirm payment by the runner.
     */
    public function confirmPayment(Request $request, RunCommitment $commitment)
    {
        // Load the item and run relationships
        $commitment->load('item.run');

        // Authorization: Only the run owner can confirm payment
        if ($request->user()->id !== $commitment->item->run->user_id) {
            return response()->json([
                'message' => 'Unauthorized. Only the runner can confirm payment.'
            ], 403);
        }

        $commitment->update([
            'payment_status' => 'confirmed'
        ]);

        return response()->json([
            'message' => 'Payment confirmed',
            'data' => $commitment
        ]);
    }
}
