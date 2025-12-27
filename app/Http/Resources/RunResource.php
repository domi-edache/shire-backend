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

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format the activity message into a human-readable string.
     */
    protected function formatActivityMessage($activity): string
    {
        $userName = $activity->user ? $activity->user->name : 'Someone';

        switch ($activity->type) {
            case 'user_joined':
                $slots = $activity->metadata['slots'] ?? 0;
                return "{$userName} joined with {$slots} slots";
            case 'payment_marked':
                return "{$userName} marked payment sent";
            case 'payment_confirmed':
                return "{$userName} confirmed payment received";
            case 'status_change':
                $newStatus = $activity->metadata['new'] ?? 'unknown';
                return "Run status changed to {$newStatus}";
            case 'comment':
                return "{$userName} left a comment";
            default:
                return "Activity: {$activity->type}";
        }
    }
}
