<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunActivityResource extends JsonResource
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
            'type' => $this->type,
            'user_name' => $this->user ? $this->user->name : 'System',
            'message' => $this->formatActivityMessage(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format the activity message into a human-readable string.
     */
    protected function formatActivityMessage(): string
    {
        $userName = $this->user ? $this->user->name : 'Someone';
        $storeName = $this->run?->store_name ?? 'the store';

        switch ($this->type) {
            case 'user_joined':
                $slots = $this->metadata['slots'] ?? 0;
                return "{$userName} joined with {$slots} slots";
            case 'host_auto_join':
                $slots = $this->metadata['slots'] ?? 0;
                return "Host created the haul (keeping {$slots} slots)";
            case 'payment_marked':
                return "{$userName} marked payment sent";
            case 'payment_confirmed':
                return "{$userName} confirmed payment received";
            case 'user_left':
                $targetName = $this->metadata['target_user_name'] ?? 'A user';
                return "{$targetName} left the haul";
            case 'user_kicked':
                $targetName = $this->metadata['target_user_name'] ?? 'A user';
                return "Host removed {$targetName}";
            case 'status_change':
                $newStatus = $this->metadata['new'] ?? 'unknown';
                return $this->formatStatusMessage($userName, $newStatus, $storeName);
            case 'comment':
                return "{$userName} left a comment";
            default:
                return "Activity: {$this->type}";
        }
    }

    /**
     * Format status change messages to be more descriptive.
     */
    protected function formatStatusMessage(string $userName, string $status, string $storeName): string
    {
        return match ($status) {
            'prepping' => "{$userName} is preparing the haul",
            'live' => "Haul is now live! Neighbors can join.",
            'heading_back' => "{$userName} is heading back from {$storeName}",
            'arrived' => "{$userName} has arrived! Time to collect.",
            'completed' => "Haul completed! Thanks everyone 🎉",
            default => "Haul status updated to {$status}",
        };
    }
}
