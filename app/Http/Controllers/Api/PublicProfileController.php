<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Review;

class PublicProfileController extends Controller
{
    /**
     * Get public profile for a user.
     */
    public function show(User $user)
    {
        // Fuzzy location (postcode district only)
        $fuzzyLocation = null;
        if ($user->postcode) {
            preg_match('/^([A-Z]{1,2}\d{1,2})/', strtoupper($user->postcode), $matches);
            $fuzzyLocation = $matches[1] ?? null;
        }

        // Recent completed hauls (last 3)
        $recentHauls = $user->runs()
            ->where('status', 'completed')
            ->latest('updated_at')
            ->take(3)
            ->get(['id', 'store_name', 'updated_at'])
            ->map(fn($run) => [
                'id' => $run->id,
                'store_name' => $run->store_name,
                'completed_at' => $run->updated_at->toIso8601String(),
            ]);

        // Reviews (last 5)
        $reviews = Review::where('host_id', $user->id)
            ->with('reviewer:id,name,avatar_path')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewer' => [
                    'id' => $review->reviewer->id,
                    'name' => $review->reviewer->name,
                    'avatar_url' => $review->reviewer->profile_photo_url,
                ],
                'created_at' => $review->created_at->toIso8601String(),
            ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'handle' => $user->handle,
            'avatar_url' => $user->profile_photo_url,
            'bio' => $user->bio,
            'fuzzy_location' => $fuzzyLocation,
            'member_since' => $user->created_at->format('Y-m-d'),
            'stats' => [
                'trust_score' => $user->trust_score,
                'hauls_hosted' => $user->hauls_hosted,
                'hauls_joined' => $user->hauls_joined,
            ],
            'recent_hauls' => $recentHauls,
            'reviews' => $reviews,
        ]);
    }
}
