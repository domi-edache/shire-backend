<?php

namespace App\Observers;

use App\Models\Run;
use App\Models\RunActivity;
use Illuminate\Support\Facades\Auth;

class RunObserver
{
    /**
     * Handle the Run "updated" event.
     */
    public function updated(Run $run): void
    {
        if ($run->isDirty('status')) {
            $newStatus = $run->status;

            RunActivity::create([
                'run_id' => $run->id,
                'user_id' => Auth::id(),
                'type' => 'status_change',
                'metadata' => [
                    'old' => $run->getOriginal('status'),
                    'new' => $newStatus,
                ],
            ]);

            // Notify Participants if status is 'heading_back' or 'arrived'
            if (in_array($newStatus, ['heading_back', 'arrived'])) {
                $run->load(['commitments.user']);

                // Get unique users who have joined (excluding the host if they somehow joined)
                $participants = $run->commitments
                    ->pluck('user')
                    ->unique('id')
                    ->reject(fn($user) => $user->id === $run->user_id);

                \Illuminate\Support\Facades\Notification::send(
                    $participants,
                    new \App\Notifications\RunStatusChangedNotification($run, $newStatus)
                );
            }
        }
    }
}
