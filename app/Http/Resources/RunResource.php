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
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_name' => $this->store_name,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'pickup_image_path' => $this->pickup_image_path,
            'payment_instructions' => $this->payment_instructions,
            'user' => $this->whenLoaded('user'),
            'items' => $this->whenLoaded('items'),
            'activity_feed' => $this->activities()
                ->with('user')
                ->latest()
                ->limit(20)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => $activity->type,
                        'user_id' => $activity->user_id,
                        'user_name' => $activity->user ? $activity->user->name : 'System',
                        'message' => $this->formatActivityMessage($activity),
                        'metadata' => $activity->metadata,
                        'created_at' => $activity->created_at,
                    ];
                }),
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
