<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Disable wrapping for this resource.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'handle' => $this->handle,
            'postcode' => $this->postcode,
            'address_line_1' => $this->address_line_1,
            'profile_photo_url' => $this->profile_photo_url,
            'avatar_url' => $this->avatar_url,
            'trust_score' => $this->trust_score,
            'hauls_hosted' => $this->hauls_hosted,
            'hauls_joined' => $this->hauls_joined,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
