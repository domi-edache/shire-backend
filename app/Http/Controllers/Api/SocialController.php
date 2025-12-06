<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    /**
     * Toggle follow/unfollow for a user.
     */
    public function toggleFollow(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Check if already following
        $isFollowing = $currentUser->following()->where('following_id', $user->id)->exists();

        if ($isFollowing) {
            // Unfollow
            $currentUser->following()->detach($user->id);
            $newStatus = false;
        } else {
            // Follow
            $currentUser->following()->attach($user->id);
            $newStatus = true;
        }

        return response()->json([
            'is_following' => $newStatus
        ]);
    }
}
