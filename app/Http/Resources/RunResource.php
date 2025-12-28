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
        $user = $request->user();
        $bulkSplit = $this->items->where('type', 'bulk_split')->first();

        // 1. Determine Context
        $isHost = $user && $user->id === $this->user_id;
        $myCommitment = $this->my_commitment ?? null;
        $isConfirmed = $myCommitment && in_array($myCommitment->status, ['confirmed', 'paid_marked']);

        // 2. Privacy Logic (The Gatekeeper)
        $canSeeDetails = $isHost || $isConfirmed;

        // 3. Location Logic
        // If hidden, show vague location (Postcode Sector e.g., "SW1A")
        // If visible, show exact coordinates (or at least provide them to the map)
        $locationData = null; // Default to null if standard GET request doesn't need geometry
        // Note: The controller already computed 'distance_string'.
        // Ideally we might want to return a vague string if !canSeeDetails

        $fuzzyLocation = "Nearby"; // Default
        // In a real app we might extract the postcode sector from the user's address or use a geocoding trick.
        // For now, let's just say "Private Location" if hidden.

        return [
            'id' => (string) $this->id,
            'store_name' => $this->store_name,
            'status' => $this->status,
            'distance' => $this->distance_string ?? 'Unknown distance',
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_taking_requests' => (bool) $this->is_taking_requests,

            // Context Flags
            'is_host' => $isHost,
            'can_cancel' => $isHost && $this->calculateCanCancel(),
            'my_commitment' => $myCommitment ? [
                'id' => (string) $myCommitment->id,
                'status' => $myCommitment->payment_status === 'unpaid' ? 'pending_payment' : $myCommitment->payment_status,
                'quantity' => (int) $myCommitment->quantity,
                'total_amount' => (float) $myCommitment->total_amount,
            ] : null,

            // Privacy-Protected Fields
            'pickup_image_url' => $canSeeDetails && $this->pickup_image_path
                ? asset('storage/' . $this->pickup_image_path)
                : null,
            'pickup_instructions' => $canSeeDetails
                ? $this->pickup_instructions
                : null,
            'payment_instructions' => $canSeeDetails
                ? $this->payment_instructions
                : null,
            'fuzzy_location' => !$canSeeDetails ? 'Visible to participants' : null,

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
                return $item->commitments->map(function ($commitment) {
                    return [
                        'commitment_id' => (string) $commitment->id,
                        'user_id' => $commitment->user_id,
                        'name' => $commitment->user ? $commitment->user->name : 'Unknown',
                        'avatar_url' => $commitment->user ? $commitment->user->profile_photo_url : null,
                        'status' => $commitment->payment_status === 'unpaid' ? 'pending_payment' : $commitment->payment_status,
                        'quantity' => $commitment->quantity,
                    ];
                });
            })->unique('user_id')->values(),

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
