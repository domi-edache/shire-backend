<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Use authenticated_user from run model (set by controller for public routes)
        // Falls back to request->user() for protected routes
        $user = $this->authenticated_user ?? $request->user();
        $isGuest = !$user;
        $bulkSplit = $this->items->where('type', 'bulk_split')->first();

        // 1. Determine Context
        $isHost = $user && (int) $user->id === (int) $this->user_id;
        $myCommitment = $this->my_commitment ?? null;
        $isConfirmed = $myCommitment && in_array($myCommitment->status, ['confirmed', 'paid_marked']);

        // 2. Privacy Logic (The Gatekeeper)
        // Guests cannot see sensitive details
        // Participants with ANY commitment (even pending) can see payment instructions
        $hasCommitment = $myCommitment !== null;
        $canSeeDetails = !$isGuest && ($isHost || $hasCommitment);

        // Debug logging
        \Log::info('RunResource debug', [
            'run_id' => $this->id,
            'user_id' => $user?->id,
            'run_user_id' => $this->user_id,
            'isHost' => $isHost,
            'canSeeDetails' => $canSeeDetails,
            'pickup_instructions' => $this->pickup_instructions,
        ]);

        // 3. Location Logic - fuzzy for guests/non-confirmed
        $fuzzyLocation = null;
        if ($isGuest) {
            // Extract postcode district (e.g., "E8 1AA" -> "E8")
            $postcode = $this->user->postcode ?? '';
            preg_match('/^([A-Z]{1,2}\d{1,2})/', strtoupper($postcode), $matches);
            $fuzzyLocation = $matches[1] ?? 'London';
        } elseif (!$canSeeDetails) {
            $fuzzyLocation = 'Visible to participants';
        }

        return [
            'id' => (string) $this->id,
            'store_name' => $this->store_name,
            'status' => $this->status,
            'distance' => $isGuest ? null : ($this->distance_string ?? 'Unknown distance'),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_taking_requests' => (bool) $this->is_taking_requests,

            // Context Flags
            'is_guest' => $isGuest,
            'is_host' => $isHost,
            'can_cancel' => $isHost && $this->calculateCanCancel(),
            'my_commitment' => $myCommitment ? [
                'id' => (string) $myCommitment->id,
                // Treat NULL or 'unpaid' as 'pending_payment' for frontend state
                'status' => in_array($myCommitment->payment_status, ['unpaid', null], true)
                    ? 'pending_payment'
                    : $myCommitment->payment_status,
                'quantity' => (int) $myCommitment->quantity,
                'total_amount' => (float) $myCommitment->total_amount,
                'can_leave' => in_array($myCommitment->payment_status, ['unpaid', null], true)
                    && $myCommitment->created_at > now()->subMinutes(30),
                'leave_window_expires_at' => $myCommitment->created_at->addMinutes(30)->toIso8601String(),
            ] : null,

            // Privacy-Protected Fields
            'pickup_image_url' => $canSeeDetails && $this->pickup_image_path
                ? asset('storage/' . $this->pickup_image_path)
                : null,
            'pickup_instructions' => $canSeeDetails
                ? ($this->pickup_instructions ?? '') // Return empty string if visible but empty
                : null, // Return null if hidden
            'payment_instructions' => $canSeeDetails
                ? ($this->payment_instructions ?? '')
                : null,
            'fuzzy_location' => $fuzzyLocation,

            // Shallow Embedded Host (Mini-Profile)
            'host' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->profile_photo_url,
                'trust_score' => $this->user->trust_score,
                'handle' => $this->user->handle,
            ],

            // Shallow Embedded Bulk Split (The Hook)
            'bulk_split' => $bulkSplit ? [
                'title' => $bulkSplit->title,
                'price_per_slot' => $bulkSplit->units_total > 0
                    ? (float) ($bulkSplit->cost / $bulkSplit->units_total)
                    : (float) $bulkSplit->cost,
                'total_slots' => (int) $bulkSplit->units_total,
                'taken_slots' => (int) $bulkSplit->units_filled,
                'progress' => $bulkSplit->units_total > 0
                    ? round(($bulkSplit->units_filled / $bulkSplit->units_total) * 100)
                    : 0,
            ] : null,

            // Participants List (Aggregated from all items)
            'participants' => $this->items->flatMap(function ($item) {
                return $item->commitments->map(function ($commitment) use ($item) {
                    $commitment->item_title = $item->title; // Attach item info if needed
                    return $commitment;
                });
            })
                ->groupBy('user_id')
                ->map(function ($commitments) {
                    $first = $commitments->first();
                    $user = $first->user;

                    // Determine aggregate status
                    // Check both 'status' and 'payment_status' columns to handle potential data inconsistencies
                    // Priority: paid_marked > unpaid/pending > confirmed
        
                    $hasPaidMarked = $commitments->contains(function ($c) {
                        return $c->payment_status === 'paid_marked' || $c->status === 'paid_marked';
                    });

                    $hasPending = $commitments->contains(function ($c) {
                        return in_array($c->payment_status, ['unpaid', 'pending']) ||
                            in_array($c->status, ['pending', 'pending_payment']);
                    });

                    $status = 'confirmed';
                    if ($hasPaidMarked) {
                        $status = 'paid_marked';
                    } elseif ($hasPending) {
                        $status = 'pending_payment';
                    }

                    // Find a relevant commitment ID for actions
                    // Prioritize the one with the 'paid_marked' status if exists
                    $actionableCommitment = $commitments->first(function ($c) {
                        return $c->payment_status === 'paid_marked' || $c->status === 'paid_marked';
                    }) ?? $commitments->first(function ($c) {
                        return in_array($c->payment_status, ['unpaid', 'pending']);
                    }) ?? $first;

                    return [
                        'user_id' => $first->user_id,
                        'name' => $user ? $user->name : 'Unknown',
                        'avatar_url' => $user ? $user->profile_photo_url : null,
                        'status' => $status,
                        'quantity' => $commitments->sum('quantity'),
                        'total_amount' => $commitments->sum('total_amount'),
                        'commitment_id' => (string) $actionableCommitment->id, // Use for actions
                    ];
                })
                ->values(),

            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'type' => $item->type,
                        'cost' => (float) $item->cost,
                        'units_total' => (int) $item->units_total,
                        'units_filled' => (int) $item->units_filled,
                        'status' => $item->status,
                    ];
                });
            }),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Determine if the run can be cancelled.
     * Returns true if no non-host participants have confirmed status.
     */
    protected function calculateCanCancel(): bool
    {
        foreach ($this->items as $item) {
            foreach ($item->commitments as $commitment) {
                // Skip host's own commitment
                if ($commitment->user_id === $this->user_id) {
                    continue;
                }
                if ($commitment->status === 'confirmed') {
                    return false;
                }
            }
        }
        return true;
    }
}
