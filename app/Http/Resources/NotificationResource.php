<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'title' => $this->data['title'] ?? 'Notification',
            'body' => $this->data['body'] ?? '',
            'run_id' => $this->data['run_id'] ?? null,
            'icon' => $this->data['icon'] ?? 'notifications',
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
