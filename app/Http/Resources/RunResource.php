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
        $bulkSplit = $this->items->where('type', 'bulk_split')->first();

        return [
            'id' => (string) $this->id,
            'store_name' => $this->store_name,
            'status' => $this->status,
            'distance' => $this->distance_string ?? 'Unknown distance',
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_taking_requests' => (bool) $this->is_taking_requests,
            'pickup_image_url' => $this->pickup_image_path ? asset('storage/' . $this->pickup_image_path) : null,

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

            // Detailed Information (Full View)
            'pickup_instructions' => $this->pickup_instructions,
            'payment_instructions' => $this->payment_instructions,
            'runner_fee' => (float) $this->runner_fee,
            'runner_fee_type' => $this->runner_fee_type,

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
                        'commitments' => $item->relationLoaded('commitments') ? $item->commitments->map(function ($commitment) {
                            return [
                                'id' => $commitment->id,
                                'user_id' => $commitment->user_id,
                                'user_name' => $commitment->user ? $commitment->user->name : 'Someone',
                                'quantity' => (int) $commitment->quantity,
                                'status' => $commitment->status,
                            ];
                        }) : [],
                    ];
                });
            }),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
