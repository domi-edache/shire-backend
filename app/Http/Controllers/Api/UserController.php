<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RunActivity;
use App\Http\Resources\RunActivityResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request)
    {
        return new \App\Http\Resources\UserResource($request->user());
    }

    /**
     * Get the authenticated user's recent activities across all their hauls.
     */
    public function activities(Request $request)
    {
        $userId = $request->user()->id;

        $activities = RunActivity::where(function ($query) use ($userId) {
            // Activities on runs the user hosts
            $query->whereHas('run', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
                // OR activities on runs the user has joined
                ->orWhereHas('run.commitments', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                // OR activities triggered by the user themselves
                ->orWhere('user_id', $userId);
        })
            ->with(['run:id,store_name', 'user:id,name,avatar'])
            ->latest()
            ->take(10)
            ->get();

        return RunActivityResource::collection($activities);
    }

    /**
     * Update the authenticated user's host settings (defaults for hauls).
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'default_payment_instructions' => 'nullable|string|max:500',
            'default_pickup_instructions' => 'nullable|string|max:500',
            'default_pickup_image' => 'nullable|image|max:2048',
        ]);

        // Handle pickup image upload
        if ($request->hasFile('default_pickup_image')) {
            $path = $request->file('default_pickup_image')
                ->store('pickup-images', 'public');
            $user->default_pickup_image_path = $path;
        }

        // Update text fields if provided
        if ($request->has('default_payment_instructions')) {
            $user->default_payment_instructions = $validated['default_payment_instructions'];
        }
        if ($request->has('default_pickup_instructions')) {
            $user->default_pickup_instructions = $validated['default_pickup_instructions'];
        }

        $user->save();

        return new \App\Http\Resources\UserResource($user);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $changes = [];

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'handle' => 'nullable|string|unique:users,handle,' . $user->id,
            'bio' => 'nullable|string|max:500',
            'postcode' => 'nullable|string|max:10',
            'address_line_1' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
        ]);

        // Track & update name
        if ($request->filled('name') && $request->name !== $user->name) {
            $changes[] = ['field' => 'name', 'old' => $user->name, 'new' => $request->name];
            $user->name = $request->name;
        }

        // Track & update handle
        if ($request->filled('handle') && $request->handle !== $user->handle) {
            $changes[] = ['field' => 'handle', 'old' => $user->handle, 'new' => $request->handle];
            $user->handle = $request->handle;
        }

        // Track & update bio
        if ($request->filled('bio') && $request->bio !== $user->bio) {
            $changes[] = ['field' => 'bio', 'old' => $user->bio, 'new' => $request->bio];
            $user->bio = $request->bio;
        }

        // Track & update address
        if ($request->filled('address_line_1') && $request->address_line_1 !== $user->address_line_1) {
            $changes[] = ['field' => 'address_line_1', 'old' => $user->address_line_1, 'new' => $request->address_line_1];
            $user->address_line_1 = $request->address_line_1;
        }

        // Track & update avatar
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('avatars', 'public');
            $changes[] = ['field' => 'avatar', 'old' => $user->avatar_path, 'new' => $path];
            if ($user->avatar_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $path;
        }

        // Track & update postcode (with re-geocoding)
        if ($request->filled('postcode') && $request->postcode !== $user->postcode) {
            $result = $this->geocodePostcode($request->postcode);
            if (!$result['success']) {
                return response()->json([
                    'message' => 'Invalid postcode',
                    'errors' => ['postcode' => [$result['error']]]
                ], 422);
            }
            $changes[] = ['field' => 'postcode', 'old' => $user->postcode, 'new' => $request->postcode];
            $user->postcode = $request->postcode;
            $user->location = DB::raw("ST_GeomFromText('POINT({$result['lng']} {$result['lat']})', 4326)");
        }

        // Save profile changes to history
        foreach ($changes as $change) {
            \App\Models\UserProfileChange::create([
                'user_id' => $user->id,
                'field' => $change['field'],
                'old_value' => $change['old'],
                'new_value' => $change['new'],
                'trigger' => 'user',
            ]);
        }

        $user->save();

        return new \App\Http\Resources\UserResource($user->fresh());
    }

    /**
     * Geocode a UK postcode using postcodes.io
     */
    private function geocodePostcode(string $postcode): array
    {
        $postcode = str_replace(' ', '', $postcode);

        try {
            $response = \Illuminate\Support\Facades\Http::get("https://api.postcodes.io/postcodes/{$postcode}");

            if ($response->successful()) {
                $result = $response->json('result');
                return [
                    'success' => true,
                    'lat' => $result['latitude'],
                    'lng' => $result['longitude'],
                ];
            } elseif ($response->status() === 404) {
                return ['success' => false, 'error' => "Hmm, we can't find that postcode."];
            } else {
                return ['success' => false, 'error' => 'Geocoding service unavailable.'];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Geocoding failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Unable to verify postcode. Try again later.'];
        }
    }
}
