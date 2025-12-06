<?php

namespace App\Services;

use App\Models\Run;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notify users about a new run.
     * 
     * Group A: Followers of the runner
     * Group B: Nearby neighbors (within 500m, excluding followers)
     */
    public function notifyNewRun(Run $run): void
    {
        $runner = $run->user;
        $storeName = $run->store_name;

        // Group A: Notify Followers
        $followers = $runner->followers;
        $followerIds = $followers->pluck('id')->toArray();

        foreach ($followers as $follower) {
            Log::info("PUSH to Follower [{$follower->id}]: {$runner->name} started a Haul at {$storeName}.");
        }

        // Group B: Notify Nearby Neighbors (excluding followers and the runner)
        $neighbors = $this->getNearbyUsers($run, 500, array_merge($followerIds, [$runner->id]));

        foreach ($neighbors as $neighbor) {
            Log::info("PUSH to Neighbor [{$neighbor->id}]: Haul nearby at {$storeName}.");
        }
    }

    /**
     * Notify buyers about a run update.
     * 
     * Sends notification to all users with confirmed commitments on this run.
     */
    public function notifyRunUpdate(Run $run, string $message): void
    {
        // Get all users with commitments on this run's items
        $buyers = User::whereHas('commitments', function ($query) use ($run) {
            $query->whereHas('item', function ($itemQuery) use ($run) {
                $itemQuery->where('run_id', $run->id);
            });
        })->get();

        foreach ($buyers as $buyer) {
            Log::info("PUSH to Buyer [{$buyer->id}]: {$message}");
        }
    }

    /**
     * Get users near a run location using PostGIS.
     * 
     * @param Run $run
     * @param int $radiusMeters
     * @param array $excludeUserIds
     * @return \Illuminate\Support\Collection
     */
    private function getNearbyUsers(Run $run, int $radiusMeters, array $excludeUserIds = []): \Illuminate\Support\Collection
    {
        // Get run location coordinates
        $runLocation = DB::selectOne(
            "SELECT ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng FROM runs WHERE id = ?",
            [$run->id]
        );

        if (!$runLocation || !$runLocation->lat || !$runLocation->lng) {
            return collect([]);
        }

        // Query users within radius using PostGIS
        $query = User::whereRaw(
            "ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
            [$runLocation->lng, $runLocation->lat, $radiusMeters]
        );

        // Exclude specified users
        if (!empty($excludeUserIds)) {
            $query->whereNotIn('id', $excludeUserIds);
        }

        return $query->get();
    }
}
